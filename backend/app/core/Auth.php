<?php
/**
 * 认证与权限相关功能
 */

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 先完成兼容迁移，再尝试恢复已信任设备的登录状态。
        if (function_exists('migrate_schema_if_needed')) {
            migrate_schema_if_needed();
        }

        // 历史缺陷：恢复信任设备时曾把设备行 id 误存为 user_id。
        // 作废无版本标记的旧会话，让其经信任设备 Cookie 重新恢复正确身份。
        if ($this->isLoggedIn() && ($_SESSION['auth_version'] ?? 0) < 2) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            session_start();
        }

        $this->restoreTrustedDevice();
    }

    /** 使用长期设备凭证恢复会话，直到后台撤销该设备。 */
    private function restoreTrustedDevice(): void
    {
        if ($this->isLoggedIn() || empty($_COOKIE['withu_device'])) {
            return;
        }

        $rawToken = trim((string)$_COOKIE['withu_device']);
        if (!preg_match('/^[a-f0-9]{32,128}$/i', $rawToken)) {
            return;
        }

        try {
            $row = $this->db->fetch(
                "SELECT u.id AS user_id, u.username, u.nickname, u.role, u.avatar, d.id AS device_id
                 FROM trusted_devices d
                 JOIN users u ON u.id = d.user_id
                 WHERE d.device_token_hash = :token
                   AND d.revoked_at IS NULL
                   AND u.status = 'active'
                   AND u.role IN ('user1','user2')
                 LIMIT 1",
                ['token' => hash('sha256', $rawToken)]
            );
            if (!$row) {
                return;
            }

            $this->setSessionUser([
                'id'       => (int)$row['user_id'],
                'username' => $row['username'],
                'nickname' => $row['nickname'],
                'role'     => $row['role'],
                'avatar'   => $row['avatar'] ?? null,
            ]);
            $this->db->update('trusted_devices', [
                'last_seen_at' => date('Y-m-d H:i:s'),
                'last_ip'      => function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? null),
            ], 'id = :id', ['id' => (int)$row['device_id']]);
        } catch (Throwable $e) {
            // 旧库尚未迁移或设备记录异常时，回退到普通登录。
        }
    }

    private function setSessionUser(array $user): void
    {
        $_SESSION['auth_version'] = 2;
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['nickname']  = $user['nickname'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['avatar']    = $user['avatar'] ?? null;
    }

    /** 登录成功后把当前浏览器登记为信任设备。 */
    private function rememberTrustedDevice(array $user): void
    {
        try {
            $rawToken = bin2hex(random_bytes(32));
            $this->db->insert('trusted_devices', [
                'user_id'           => (int)$user['id'],
                'device_token_hash' => hash('sha256', $rawToken),
                'device_name'       => $_SERVER['HTTP_USER_AGENT'] ?? 'Web browser',
                'last_ip'            => function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? null),
                'last_seen_at'       => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('withu_device', $rawToken, [
                'expires'  => time() + 315360000,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } catch (Throwable $e) {
            // 设备表不可用时仍保留普通 session 登录。
        }
    }

    /**
     * 用户登录（带简单防爆破）
     */
    public function login(string $username, string $password): bool
    {
        $username = trim($username);
        $password = (string) $password;

        if ($username === '' || $password === '') {
            return false;
        }

        // 统一使用配置中的防爆破参数（config/config.php 已定义）
        $ip  = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $now = time();

        // 确保存在登录尝试记录表（幂等创建）
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `login_attempts` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) DEFAULT NULL,
                    `ip` varchar(45) DEFAULT NULL,
                    `success` tinyint(1) NOT NULL DEFAULT 0,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_username_ip_time` (`username`,`ip`,`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录尝试记录';
            ");
        } catch (Exception $e) {
            // 表创建失败时不影响主流程，只回退到无持久化记录
        }

        // 基于账号 + IP 的防爆破：统计最近窗口期内的失败次数
        try {
            $windowStart = date('Y-m-d H:i:s', $now - LOGIN_ATTEMPT_WINDOW);
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS c, MAX(created_at) AS last_fail
                 FROM login_attempts
                 WHERE username = :u AND ip = :ip AND success = 0 AND created_at >= :start",
                [
                    'u'     => strtolower($username),
                    'ip'    => $ip,
                    'start' => $windowStart,
                ]
            );

            $failCount = $row ? (int) ($row['c'] ?? 0) : 0;
            $lastFail  = $row && !empty($row['last_fail']) ? strtotime($row['last_fail']) : 0;

            if ($failCount >= LOGIN_MAX_ATTEMPTS && $lastFail && ($now - $lastFail) < LOGIN_LOCKOUT_SECONDS) {
                // 仍在封禁期内，直接拒绝登录
                return false;
            }
        } catch (Exception $e) {
            // 查询失败时忽略，继续正常登录流程
        }

        // 基于 IP 的全局防爆破：限制同一 IP 在窗口期内的总失败次数
        try {
            $ipRow = $this->db->fetch(
                "SELECT COUNT(*) AS c, MAX(created_at) AS last_fail
                 FROM login_attempts
                 WHERE ip = :ip AND success = 0 AND created_at >= :start",
                [
                    'ip'    => $ip,
                    'start' => $windowStart,
                ]
            );

            $ipFailCount = $ipRow ? (int) ($ipRow['c'] ?? 0) : 0;
            $ipLastFail  = $ipRow && !empty($ipRow['last_fail']) ? strtotime($ipRow['last_fail']) : 0;

            // 同一 IP 在窗口期内失败次数超过 20 次则临时封禁（公网环境更严格）
            if ($ipFailCount >= 20 && $ipLastFail && ($now - $ipLastFail) < LOGIN_LOCKOUT_SECONDS) {
                return false;
            }
        } catch (Exception $e) {
            // 查询失败时忽略
        }

        // 继续登录校验：仅允许情侣角色（user1 / user2）登录
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? AND status = 'active' AND role IN ('user1','user2')",
            [$username]
        );

        $loginSuccess = $user && password_verify($password, $user['password']);

        // 记录登录尝试结果（最佳努力，不影响主流程）
        try {
            $this->db->insert('login_attempts', [
                'username'   => strtolower($username),
                'ip'         => $ip,
                'success'    => $loginSuccess ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s', $now),
            ]);
        } catch (Exception $e) {
            // 记录失败忽略
        }

        if ($loginSuccess) {
            // 登录成功：刷新 session id 防止会话固定
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $this->setSessionUser($user);
            $this->touchLastLogin((int)$user['id']);
            $this->rememberTrustedDevice($user);
            return true;
        }
        // 登录失败时增加微小延迟，进一步降低暴力破解效率
        usleep(200000); // 约 200ms
        return false;
    }

    /** 刷新最近活跃时间（前台在线徽标以此判定：10 分钟内视为在线）。 */
    public function touchLastLogin(int $userId): void
    {
        try {
            $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        } catch (Throwable $e) { /* 老库字段缺失时忽略 */ }
    }

    /**
     * 用户注册
     */
    public function register(string $username, string $password, string $nickname, string $role = 'user1', ?string $gender = null, ?string $qq = null): array
    {
        // 基础输入校验：长度与字符集限制
        $username = trim($username);
        $nickname = trim($nickname);
        $role     = trim($role);
        $qq       = $qq === null ? null : trim($qq);

        if ($username === '' || $password === '' || $nickname === '') {
            return ['success' => false, 'message' => '请填写所有必填项'];
        }

        // 用户名：限制为 3~32 位的字母、数字（用于登录）
        if (!preg_match('/^[a-zA-Z0-9]{3,32}$/', $username)) {
            return ['success' => false, 'message' => '用户名格式不合法（仅允许字母和数字，长度 3~32 位）'];
        }

        // 昵称：控制在 1~32 个字符以内（UTF-8 长度）
        if (mb_strlen($nickname, 'UTF-8') > 32) {
            return ['success' => false, 'message' => '昵称长度不能超过 32 个字符'];
        }

        // 密码：至少 8 位
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => '密码长度不能少于 8 位'];
        }

        // 角色仅允许 user1 / user2
        if (!in_array($role, ['user1', 'user2'], true)) {
            return ['success' => false, 'message' => '角色不合法'];
        }

        // 性别：显式传入时仅允许 male / female；未传入时按角色约定推导（user1=男，user2=女）
        if ($gender !== null) {
            $gender = trim((string) $gender);
            if (!in_array($gender, ['male', 'female'], true)) {
                return ['success' => false, 'message' => '性别不合法'];
            }
        } else {
            $gender = $role === 'user2' ? 'female' : 'male';
        }

        // QQ 号：5~11 位数字且首位不为 0；填写后直接启用 QQ 头像
        if ($qq !== null && $qq !== '') {
            if (!preg_match('/^[1-9][0-9]{4,10}$/', $qq)) {
                return ['success' => false, 'message' => 'QQ 号格式不正确（应为 5~11 位数字）'];
            }
        } else {
            $qq = null;
        }

        // 限制最多只能有两位活跃用户（情侣两人）
        $countRow = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM users WHERE status = 'active'"
        );
        $activeCount = $countRow ? (int) $countRow['c'] : 0;
        if ($activeCount >= 2) {
            return ['success' => false, 'message' => '当前已注册满两位用户，已关闭注册'];
        }

        // 检查用户名是否已存在
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );

        if ($existing) {
            return ['success' => false, 'message' => '用户名已存在'];
        }

        // 检查角色是否已被使用（user1 / user2 只能各有一人）
        if ($role === 'user1' || $role === 'user2') {
            $existingRole = $this->db->fetch(
                "SELECT id FROM users WHERE role = ?",
                [$role]
            );

            if ($existingRole) {
                return ['success' => false, 'message' => '该角色已被使用'];
            }
        }

        $data = [
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'nickname'   => $nickname,
            'role'       => $role,
            'gender'     => $gender,
            'avatar'     => '/assets/images/default-avatar.svg',
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($qq !== null) {
            // 注册即绑定 QQ 号，头像默认走官方 QQ 头像接口（与个人资料页 avatar_source=qq 的规则一致）
            $data['qq']     = $qq;
            $data['avatar'] = 'https://q1.qlogo.cn/g?b=qq&nk=' . urlencode($qq) . '&s=640';
        }

        $userId = $this->db->insert('users', $data);

        return ['success' => true, 'user_id' => $userId];
    }

    /**
     * 用户退出登录
     */
    public function logout(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        // 清除信任设备 Cookie，防止自动恢复登录
        if (isset($_COOKIE['withu_device'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('withu_device', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE['withu_device']);
        }
        return true;
    }

    /** 当前用户的信任设备列表，供后台解绑。 */
    public function listTrustedDevices(): array
    {
        if (!$this->isLoggedIn()) return [];
        try {
            return $this->db->fetchAll(
                "SELECT id, device_name, last_ip, last_seen_at, created_at, revoked_at
                 FROM trusted_devices WHERE user_id = :uid ORDER BY created_at DESC",
                ['uid' => (int)$_SESSION['user_id']]
            );
        } catch (Throwable $e) { return []; }
    }

    public function revokeTrustedDevice(int $deviceId): bool
    {
        if (!$this->isLoggedIn() || $deviceId <= 0) return false;
        try {
            $this->db->update('trusted_devices', ['revoked_at' => date('Y-m-d H:i:s')], 'id = :id AND user_id = :uid', [
                'id' => $deviceId, 'uid' => (int)$_SESSION['user_id']
            ]);
            return true;
        } catch (Throwable $e) { return false; }
    }

    /**
     * 是否已登录
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * 获取当前登录用户信息
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nickname' => $_SESSION['nickname'],
            'role'     => $_SESSION['role'],
            'avatar'   => $_SESSION['avatar']
        ];
    }

    /**
     * 获取另一半（情侣另一方）的用户信息
     */
    public function getPartner(): ?array
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return null;
        }

        $partnerRole = $currentUser['role'] === 'user1' ? 'user2' : 'user1';

        $partner = $this->db->fetch(
            "SELECT u.*
             FROM couple_invites ci
             JOIN users cu ON cu.id = :current_id
             JOIN users u ON u.status = 'active'
               AND u.role = :partner_role
               AND (
                 (ci.inviter_id = cu.id AND ci.invitee_id = u.id)
                 OR
                 (ci.inviter_id = u.id AND ci.invitee_id = cu.id)
               )
             WHERE ci.status = 'accepted'
             ORDER BY u.created_at ASC, u.id ASC
             LIMIT 1",
            [
                'current_id'   => (int)$currentUser['id'],
                'partner_role' => $partnerRole,
            ]
        );

        if ($partner) {
            return $partner;
        }

        return $this->db->fetch(
            "SELECT * FROM users
             WHERE role = :partner_role AND status = 'active'
             ORDER BY created_at ASC, id ASC
             LIMIT 1",
            ['partner_role' => $partnerRole]
        );
    }

    /**
     * 需要登录后才能访问的页面调用
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * 要求当前登录用户具有指定角色之一
     * 用于后台等敏感区域的访问控制
     *
     * @param string[] $roles
     */
    public function requireRole(array $roles): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
            header('Location: /login.php');
            exit;
        }

        $currentRole = (string) $_SESSION['role'];
        if (!in_array($currentRole, $roles, true)) {
            // 非法访问后台或受限区域时，统一跳转到首页
            header('Location: /');
            exit;
        }
    }

    /**
     * 更新当前用户资料
     */
    public function updateProfile(array $data): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        // 确保用户资料相关扩展字段在 users 表中存在（兼容老版本数据库结构）
        $this->ensureUserProfileColumns();

        $userId = $_SESSION['user_id'];

        // 密码单独处理
        if (isset($data['new_password']) && $data['new_password'] !== '') {
            $data['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        unset($data['new_password']);

        if (isset($data['password']) && $data['password'] === '') {
            unset($data['password']);
        }

        $this->db->update('users', $data, 'id = :id', ['id' => $userId]);

        // 同步更新 session
        if (isset($data['nickname'])) {
            $_SESSION['nickname'] = $data['nickname'];
        }
        if (isset($data['avatar'])) {
            $_SESSION['avatar'] = $data['avatar'];
        }
    }

    /**
     * 兼容性处理：确保 users 表中存在新版本资料字段（例如 qq、avatar_source）
     * 仅在需要更新资料时检查一次，避免手工执行 SQL
     */
    private function ensureUserProfileColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            // 检查并自动创建 qq 字段
            $row = $this->db->fetch("SHOW COLUMNS FROM `users` LIKE 'qq'");
            if (!$row) {
                $this->db->query("ALTER TABLE `users` ADD COLUMN `qq` varchar(32) DEFAULT NULL COMMENT 'QQ 号'");
            }

            // 检查并自动创建 avatar_source 字段（记录头像来源：上传/QQ）
            $row = $this->db->fetch("SHOW COLUMNS FROM `users` LIKE 'avatar_source'");
            if (!$row) {
                $this->db->query("ALTER TABLE `users` ADD COLUMN `avatar_source` varchar(20) DEFAULT 'qq' COMMENT '头像来源'");
            }
        } catch (Exception $e) {
            // 兼容环境中无权限 ALTER TABLE 时，不中断主流程，仅放弃自动修复
        }

        $checked = true;
    }
}
