package com.hienao.openlist2strm.config;

import java.nio.ByteBuffer;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.Arrays;
import java.util.Base64;
import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

/**
 * 豆瓣登录 Cookie 的透明加解密工具（AES-256-GCM）。
 *
 * <p>系统配置文件 systemconf.json 中的 scraping.doubanCookie 属于敏感凭据，落盘前加密、读取后解密，
 * 浏览器回显时以掩码代替真实值。旧版明文（无 {@link #PREFIX} 前缀）原样透传，首次保存后自动迁移为密文。
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
public class DoubanCookieCipher {

  /** 密文前缀，用于区分加密数据与旧版明文数据。 */
  public static final String PREFIX = "enc:v1:";

  private static final String TRANSFORMATION = "AES/GCM/NoPadding";
  private static final String ALGORITHM = "AES";
  private static final int NONCE_LENGTH = 12;
  private static final int TAG_LENGTH_BITS = 128;
  private static final SecureRandom SECURE_RANDOM = new SecureRandom();

  private final byte[] key;

  public DoubanCookieCipher(@Value("${douban-cookie.key}") String secret) {
    this.key = deriveKey(secret);
    if (secret == null || secret.isBlank() || "secret".equals(secret)) {
      log.warn("豆瓣 Cookie 加密密钥使用默认值，生产环境请通过 DOUBAN_COOKIE_KEY 配置强密钥");
    }
  }

  /** 加密明文，返回带 {@link #PREFIX} 前缀的密文。 */
  public String encrypt(String plaintext) {
    if (plaintext == null) {
      return null;
    }
    try {
      byte[] nonce = new byte[NONCE_LENGTH];
      SECURE_RANDOM.nextBytes(nonce);
      Cipher cipher = Cipher.getInstance(TRANSFORMATION);
      cipher.init(
          Cipher.ENCRYPT_MODE,
          new SecretKeySpec(key, ALGORITHM),
          new GCMParameterSpec(TAG_LENGTH_BITS, nonce));
      byte[] ciphertext = cipher.doFinal(plaintext.getBytes(StandardCharsets.UTF_8));
      ByteBuffer buffer = ByteBuffer.allocate(nonce.length + ciphertext.length);
      buffer.put(nonce).put(ciphertext);
      return PREFIX + Base64.getEncoder().encodeToString(buffer.array());
    } catch (Exception e) {
      throw new IllegalStateException("豆瓣 Cookie 加密失败", e);
    }
  }

  /** 解密存储值；旧版明文（无前缀）原样返回。 */
  public String decrypt(String stored) {
    if (stored == null || stored.isBlank()) {
      return stored;
    }
    if (!stored.startsWith(PREFIX)) {
      return stored;
    }
    try {
      byte[] payload = Base64.getDecoder().decode(stored.substring(PREFIX.length()));
      if (payload.length <= NONCE_LENGTH) {
        throw new IllegalArgumentException("密文长度非法");
      }
      byte[] nonce = Arrays.copyOfRange(payload, 0, NONCE_LENGTH);
      byte[] ciphertext = Arrays.copyOfRange(payload, NONCE_LENGTH, payload.length);
      Cipher cipher = Cipher.getInstance(TRANSFORMATION);
      cipher.init(
          Cipher.DECRYPT_MODE,
          new SecretKeySpec(key, ALGORITHM),
          new GCMParameterSpec(TAG_LENGTH_BITS, nonce));
      return new String(cipher.doFinal(ciphertext), StandardCharsets.UTF_8);
    } catch (Exception e) {
      log.error("豆瓣 Cookie 解密失败，密钥可能已变更，请重新填写该配置的 Cookie");
      throw new IllegalStateException("豆瓣 Cookie 解密失败，请检查密钥配置或重新填写 Cookie", e);
    }
  }

  private static byte[] deriveKey(String secret) {
    try {
      return MessageDigest.getInstance("SHA-256").digest(secret.getBytes(StandardCharsets.UTF_8));
    } catch (Exception e) {
      throw new IllegalStateException("豆瓣 Cookie 加密密钥初始化失败", e);
    }
  }
}
