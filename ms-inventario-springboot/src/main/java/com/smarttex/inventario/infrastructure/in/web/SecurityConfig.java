package com.smarttex.inventario.infrastructure.in.web;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.web.SecurityFilterChain;

@Configuration
public class SecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .csrf(csrf -> csrf.disable()) // Desactiva CSRF para pruebas en Postman
            .authorizeHttpRequests(auth -> auth
                .anyRequest().permitAll() // Permite el acceso libre a todas las rutas sin pedir token
            );
        return http.build();
    }
}