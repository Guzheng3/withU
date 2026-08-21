    </main>
</div>

<nav class="admin-tabbar">
    <div class="admin-tabbar-inner">
        <a href="/admin/index.php"
           class="admin-tab-item <?php echo $adminPage === 'dashboard' ? 'admin-tab-item-active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>概览</span>
        </a>
        <a href="/admin/articles.php"
           class="admin-tab-item <?php echo in_array($adminPage, ['articles', 'albums', 'messages', 'events', 'map'], true) ? 'admin-tab-item-active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>内容</span>
        </a>
        <a href="/admin/player_art.php"
           class="admin-tab-item <?php echo in_array($adminPage, ['together_settings', 'player_settings', 'player_art', 'strm'], true) ? 'admin-tab-item-active' : ''; ?>">
            <i class="fas fa-film"></i>
            <span>影视</span>
        </a>
        <button type="button" class="admin-tab-item admin-tab-item-menu" data-admin-toggle="drawer" aria-label="打开后台菜单" aria-controls="admin-drawer" aria-expanded="false">
            <i class="fas fa-bars-staggered"></i>
            <span>菜单</span>
        </button>
    </div>
</nav>

<div class="admin-modal-backdrop" id="adminConfirmBackdrop">
    <div class="admin-modal">
        <div class="admin-modal-header">操作确认</div>
        <div class="admin-modal-body" id="adminConfirmMessage">
            确认执行该操作？
        </div>
        <div class="admin-modal-actions">
            <button type="button" class="btn btn-secondary" data-admin-confirm="cancel">取消</button>
            <button type="button" class="btn btn-primary" data-admin-confirm="ok">确定</button>
        </div>
    </div>
</div>

<script src="/admin-assets/js/admin_v2.js?v=withu-admin-20260720-3"></script>
</body>
</html>
