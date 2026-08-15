package com.hienao.openlist2strm.config.security;

import com.hienao.openlist2strm.service.SystemConfigService;
import lombok.RequiredArgsConstructor;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.HttpMethod;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.config.annotation.authentication.configuration.AuthenticationConfiguration;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.config.annotation.web.configurers.AbstractHttpConfigurer;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.authentication.UsernamePasswordAuthenticationFilter;
import org.springframework.security.web.util.matcher.*;
import org.springframework.web.cors.CorsConfigurationSource;

@Configuration
@EnableWebSecurity
@EnableMethodSecurity
@RequiredArgsConstructor
public class WebSecurityConfig {

  private final UserDetailsServiceImpl userDetailsService;

  private final Jwt jwt;

  private final CorsConfigurationSource corsConfigurationSource;

  private final SystemConfigService systemConfigService;

  @Bean
  public AuthenticationManager authenticationManager(
      AuthenticationConfiguration authenticationConfiguration) throws Exception {
    return authenticationConfiguration.getAuthenticationManager();
  }

  @Bean
  public RequestMatcher publicEndPointMatcher() {
    return new OrRequestMatcher(
        // API 认证端点（统一使用 /api 前缀）
        new AntPathRequestMatcher("/api/auth/sign-in", HttpMethod.POST.name()),
        new AntPathRequestMatcher("/api/auth/sign-up", HttpMethod.POST.name()),
        new AntPathRequestMatcher("/api/auth/check-user", HttpMethod.GET.name()),
        // 版本检查接口（无需认证）
        new AntPathRequestMatcher("/api/version/**"),
        // 测试接口（无需认证）
        new AntPathRequestMatcher("/api/test/**"),
        // 日志接口（无需认证）
        new AntPathRequestMatcher("/api/logs/**"),
        // STRM 播放代理（外部媒体库读取 strm 时无登录态，需匿名访问）
        new AntPathRequestMatcher("/api/strm-play/**"),
        // 外部媒体库接口（独立 API Key 鉴权，由 ExternalApiFilter 处理）
        new AntPathRequestMatcher("/api/external/**"),
        // WebSocket 连接（无需认证）
        new AntPathRequestMatcher("/ws/**"),
        // 其他公开端点
        new AntPathRequestMatcher("/actuator/health", HttpMethod.GET.name()),
        new AntPathRequestMatcher("/v3/api-docs/**", HttpMethod.GET.name()),
        new AntPathRequestMatcher("/swagger-ui/**", HttpMethod.GET.name()),
        new AntPathRequestMatcher("/swagger-ui.html", HttpMethod.GET.name()),
        new AntPathRequestMatcher("/error"));
  }

  @Bean
  public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
    RestfulAuthenticationEntryPointHandler restfulAuthenticationEntryPointHandler =
        new RestfulAuthenticationEntryPointHandler();
    /*
    <Stateless API CSRF protection>
    http.csrf(csrf -> csrf.csrfTokenRepository(CookieCsrfTokenRepository.withHttpOnlyFalse()))
    */
    http.cors(corsConfigurer -> corsConfigurer.configurationSource(corsConfigurationSource));
    http.csrf(AbstractHttpConfigurer::disable)
        .authorizeHttpRequests(
            authorize ->
                authorize
                    .requestMatchers(publicEndPointMatcher())
                    .permitAll()
                    .anyRequest()
                    .authenticated())
        .exceptionHandling(
            (exceptionHandling) ->
                exceptionHandling
                    .accessDeniedHandler(restfulAuthenticationEntryPointHandler)
                    .authenticationEntryPoint(restfulAuthenticationEntryPointHandler))
        .sessionManagement(
            session -> session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
        .addFilterAt(
            new JwtAuthenticationFilter(jwt, userDetailsService),
            UsernamePasswordAuthenticationFilter.class)
        .addFilterAfter(
            new ExternalApiFilter(systemConfigService),
            JwtAuthenticationFilter.class);
    return http.build();
  }
}
