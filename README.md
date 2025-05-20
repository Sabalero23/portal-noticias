# Portal de Noticias 24/7

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)](https://php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-%3E%3D5.7-orange)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-purple)](https://web.dev/progressive-web-apps/)

## 📰 Descripción

**Portal de Noticias 24/7** es un sistema completo de gestión de contenido (CMS) especializado en portales de noticias. Desarrollado en PHP con MySQL, ofrece una solución robusta y escalable para la publicación, administración y distribución de contenido periodístico en tiempo real.

### ✨ Características Principales

- 🔐 **Sistema de autenticación robusto** con roles múltiples
- 📝 **Editor WYSIWYG** para creación de contenido
- 🏷️ **Sistema de categorías y etiquetas** 
- 💬 **Sistema de comentarios** con moderación
- 📈 **Sistema de publicidad** con métricas
- 🗳️ **Encuestas interactivas** 
- 📱 **PWA (Progressive Web App)** ready
- 🌤️ **Integración con API del clima**
- 📊 **Estadísticas y analytics**
- 🎨 **Sistema de temas** personalizable
- ✉️ **Newsletter** y suscripciones
- 📱 **Diseño responsive** 

## 🚀 Características Técnicas

### Arquitectura
- **Framework**: Vanilla PHP 7.4+
- **Base de datos**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Bootstrap 5, JavaScript ES6
- **Patrón de diseño**: MVC + Singleton
- **APIs**: OpenWeatherMap, PHPMailer
- **Seguridad**: Hashing bcrypt, tokens CSRF, sanitización XSS

### Módulos Principales
1. **Gestión de Noticias**: Crear, editar, publicar noticias
2. **Administración de Usuarios**: Roles (Admin, Editor, Autor, Suscriptor)
3. **Sistema de Categorías**: Organización jerárquica del contenido
4. **Gestión de Comentarios**: Moderación y respuestas
5. **Sistema de Publicidad**: Anuncios con métricas de rendimiento
6. **Encuestas**: Votaciones interactivas
7. **Newsletter**: Suscripciones y envío masivo
8. **Media Manager**: Gestión centralizada de archivos
9. **Estadísticas**: Analytics y reportes

## 📋 Requisitos del Sistema

### Requerimientos Mínimos
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior / MariaDB 10.3+
- **Apache/Nginx**: Con mod_rewrite habilitado
- **Memoria PHP**: 128MB mínimo (256MB recomendado)
- **Espacio en disco**: 500MB para instalación base

### Extensiones PHP Requeridas
```
- pdo_mysql
- mbstring
- openssl
- curl
- gd o imagick
- json
- zip
```

### Librerías y Dependencias
- **Composer**: Gestor de dependencias PHP
- **PHPMailer**: v6.10+ (gestión de correos SMTP)
- **Bootstrap**: v5.3 (framework CSS)
- **FontAwesome**: v6.4 (iconografía)

## 🔧 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/Sabalero23/portal-noticias.git
cd portal-noticias
```

### 2. Configurar la Base de Datos
```bash
# Crear la base de datos
mysql -u root -p
CREATE DATABASE portal_noticias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Importar la estructura
mysql -u root -p portal_noticias < db/schema.sql
```

### 3. Configurar el Sistema
```bash
# Copiar archivo de configuración
cp includes/config.example.php includes/config.php

# Editar configuración
nano includes/config.php
```

### 4. Configurar Variables de Entorno
Editar `includes/config.php`:

```php
// Base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_NAME', 'portal_noticias');

// URL del sitio
define('SITE_URL', 'https://tu-dominio.com');

// Correo SMTP (opcional)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'tu_email@gmail.com');
define('MAIL_PASSWORD', 'tu_contraseña');
```

### 5. Configurar Permisos
```bash
# Permisos de directorios
chmod 755 assets/img/uploads/
chmod 755 assets/img/news/
chmod 755 assets/img/ads/
chmod 755 cache/ # si existe

# Permisos de archivos de configuración
chmod 644 includes/config.php
```

### 6. Instalar Dependencias (Opcional)
```bash
# Si usas Composer
composer install
```

### 7. Configurar Servidor Web

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Seguridad
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /includes/ {
    deny all;
}
```

## 🔑 Configuración Inicial

### 1. Acceder al Panel de Administración
```
URL: https://tu-sitio.com/admin/
Usuario: admin
Contraseña: (definida en instalación)
```

### 2. Configuraciones Básicas
1. **General**: Nombre del sitio, descripción, logo
2. **APIs**: OpenWeatherMap, Google Analytics
3. **SMTP**: Configuración de correo saliente
4. **Temas**: Seleccionar tema activo
5. **Redes Sociales**: Enlaces a perfiles

### 4. Crear Contenido Inicial
1. **Categorías**: Crear categorías principales
2. **Usuarios**: Añadir editores y autores
3. **Noticias**: Publicar primeras noticias
4. **Publicidad**: Configurar anuncios

## 📁 Estructura del Proyecto

```
portal-noticias/
├── index.php                 # Página principal
├── news.php                  # Detalle de noticias
├── category.php              # Noticias por categoría
├── admin/                    # Panel de administración
│   ├── dashboard.php         
│   ├── news/                 # Gestión de noticias
│   ├── categories/           # Gestión de categorías
│   ├── users/                # Gestión de usuarios
│   ├── ads/                  # Gestión de publicidad
│   ├── tutorial.php          # Tutorial del sistema
│   └── settings/             # Configuraciones
├── includes/                 # Archivos del sistema
│   ├── config.php            # Configuración
│   ├── db_connection.php     # Conexión BD
│   ├── functions.php         # Funciones generales
│   └── auth.php              # Autenticación
├── assets/                   # Recursos estáticos
│   ├── js/                   # JavaScript
│   ├── img/                  # Imágenes
│   └── themes/               # Temas
├── db/
│   └── schema.sql            # Estructura de BD
└── composer.json             # Dependencias
```

## 🎨 Sistema de Temas

### Temas Disponibles
- **Default**: Tema clásico de 3 columnas
- **Modern**: Diseño contemporáneo
- **Minimalist**: Enfoque limpio y minimalista
- **Technology**: Especializado en tecnología
- **Sports**: Optimizado para deportes

### Crear Tema Personalizado
```bash
# Crear directorio del tema
mkdir assets/themes/mi-tema/

# Archivos requeridos
touch assets/themes/mi-tema/styles.css
touch assets/themes/mi-tema/responsive.css
touch assets/themes/mi-tema/theme.json
```

Ejemplo `theme.json`:
```json
{
  "name": "Mi Tema",
  "description": "Descripción del tema",
  "version": "1.0",
  "author": "Tu Nombre",
  "colors": {
    "primary": "#007bff",
    "secondary": "#6c757d"
  }
}
```

## 🔌 APIs Integradas

### OpenWeatherMap
```php
// Configurar en admin/settings/api.php
'weather_api_key' => 'tu_api_key',
'weather_api_city' => 'Buenos Aires',
'weather_api_units' => 'metric'
```

### Google Analytics
```php
// ID de seguimiento
'analytics_id' => 'GA-XXXXXXXX-X'
```

### SMTP (PHPMailer)
```php
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => '587',
'smtp_username' => 'email@example.com',
'smtp_password' => 'contraseña'
```

## 👥 Sistema de Usuarios

### Roles Disponibles
- **Admin**: Control total del sistema
- **Editor**: Gestión de contenido y usuarios
- **Autor**: Publicación de noticias propias
- **Suscriptor**: Acceso básico y comentarios

### Permisos por Rol
| Función | Admin | Editor | Autor | Suscriptor |
|---------|-------|--------|-------|------------|
| Gestionar usuarios | ✅ | ✅ | ❌ | ❌ |
| Publicar noticias | ✅ | ✅ | ✅ | ❌ |
| Moderar comentarios | ✅ | ✅ | ❌ | ❌ |
| Gestionar publicidad | ✅ | ❌ | ❌ | ❌ |
| Configurar sistema | ✅ | ❌ | ❌ | ❌ |

## 📊 Características SEO

### Optimizaciones Incluidas
- ✅ **URLs amigables** (slugs personalizables)
- ✅ **Meta tags** dinámicos
- ✅ **Open Graph** para redes sociales
- ✅ **Schema.org** (JSON-LD)
- ✅ **Sitemap XML** automático
- ✅ **Breadcrumbs** estructurados
- ✅ **Tiempos de carga** optimizados

### Configuración SEO
```php
// En cada noticia
$pageTitle = $news['title'] . ' - ' . SITE_NAME;
$metaDescription = $news['excerpt'];
$ogImage = $news['image'];
$canonicalUrl = SITE_URL . '/news/' . $news['slug'];
```

## 🔒 Seguridad

### Implementaciones de Seguridad
- **Sanitización** de entradas con `htmlspecialchars()`
- **Preparación de consultas** SQL (PDO)
- **Tokens CSRF** en formularios
- **Hashing** de contraseñas con bcrypt
- **Validación** de tipos de archivo
- **Escape** de salidas HTML
- **Protección** de directorios sensibles

### Configuraciones de Seguridad
```php
// En includes/config.php
define('DEBUG_MODE', false);  // Producción
define('TOKEN_SALT', 'salt_único_aleatorio');
define('SESSION_EXPIRE', 1800); // 30 minutos
```

## 📱 PWA (Progressive Web App)

### Características PWA
- ✅ **Instalable** en dispositivos móviles
- ✅ **Funcionamiento offline** básico
- ✅ **Notificaciones push** (configurables)
- ✅ **Iconos** adaptativos
- ✅ **Splash screen** personalizada
- ✅ **Shortcuts** de aplicación

### Configurar PWA
```json
// manifest.json
{
  "name": "Portal de Noticias",
  "short_name": "Noticias",
  "start_url": "/",
  "display": "standalone",
  "theme_color": "#2196F3"
}
```

## 🚀 Optimización de Rendimiento

### Mejores Prácticas Implementadas
- **Consultas SQL** optimizadas con índices
- **Carga lazy** de imágenes
- **Compresión** de CSS/JS
- **Cache** de configuraciones
- **Sprites** de iconos
- **Optimización** de imágenes automática

### Configuraciones de Cache
```php
// Cache de configuraciones
$settings = getSetting('cache_enabled', true);
$cacheTime = getSetting('cache_duration', 3600);
```

## 📧 Sistema de Newsletter

### Funcionalidades
- 📝 **Suscripciones** con confirmación por email
- 📊 **Segmentación** por categorías
- 📈 **Métricas** de apertura y clicks
- 📋 **Exportación** de suscriptores
- 🎯 **Envío masivo** programado

### Configurar Newsletter
```php
// SMTP para envíos masivos
define('MAIL_HOST', 'smtp.mailgun.org');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'apikey');
define('MAIL_PASSWORD', 'tu_api_key');
```

## 📈 Analytics y Estadísticas

### Métricas Disponibles
- 📊 **Visitas** por noticia
- 👥 **Usuarios** únicos
- 📱 **Dispositivos** y navegadores
- 🔗 **Referencias** de tráfico
- 💰 **Rendimiento** de publicidad
- 📈 **Tendencias** temporales

### Integración con Google Analytics
```javascript
// Tracking personalizado
gtag('event', 'page_view', {
  'article_id': news_id,
  'category': news_category,
  'author': news_author
});
```

## 🛠️ Mantenimiento

### Tareas de Mantenimiento Regulares
- 🗂️ **Limpiar** logs antiguos
- 🖼️ **Optimizar** imágenes no utilizadas
- 📊 **Respaldar** base de datos
- 🔄 **Actualizar** dependencias
- 🔍 **Revisar** logs de errores

### Scripts de Mantenimiento
```bash
# Backup automático
mysqldump -u usuario -p portal_noticias > backup_$(date +%Y%m%d).sql

# Limpiar cache
find cache/ -type f -mtime +7 -delete

# Optimizar imágenes
find assets/img/uploads/ -name "*.jpg" -exec jpegoptim {} \;
```

## 🚨 Troubleshooting

### Problemas Comunes

#### 🔍 **Slider no se muestra**
```php
// Verificar noticias destacadas
SELECT COUNT(*) FROM news WHERE featured = 1 AND status = 'published';
```

#### 🌤️ **API del clima no funciona**
```php
// Verificar configuración
$apiKey = getSetting('weather_api_key');
if (empty($apiKey) || strpos($apiKey, '_here') !== false) {
    // Configurar API key válida
}
```

#### 📧 **Emails no se envían**
```php
// Verificar configuración SMTP
$smtpEnabled = getSetting('enable_smtp', '0') === '1';
$smtpHost = getSetting('smtp_host');
```

#### 🔐 **Error de permisos**
```bash
# Ajustar permisos
chmod 755 assets/img/uploads/
chown www-data:www-data assets/img/uploads/
```

### Logs de Sistema
```php
// Habilitar logs en config.php
define('DEBUG_MODE', true);

// Ubicación de logs
error_log("Debug: " . $variable);
```

## 🔄 Actualizaciones

### Proceso de Actualización
1. **Backup** completo del sitio y BD
2. **Descargar** nueva versión
3. **Reemplazar** archivos (excepto config.php)
4. **Ejecutar** scripts de migración
5. **Verificar** funcionalidad

### Script de Migración
```bash
#!/bin/bash
# backup.sh
cp -r portal-noticias portal-noticias-backup-$(date +%Y%m%d)
mysqldump -u usuario -p portal_noticias > backup_$(date +%Y%m%d).sql
```

## 🤝 Contribuir

### Cómo Contribuir
1. **Fork** del repositorio
2. **Crear** rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. **Commit** cambios (`git commit -am 'Add nueva funcionalidad'`)
4. **Push** a la rama (`git push origin feature/nueva-funcionalidad`)
5. **Crear** Pull Request

### Estándares de Código
- **PSR-12** para PHP
- **Comentarios** en español
- **Variables** en camelCase
- **Funciones** descriptivas
- **Validación** de entradas
- **Manejo** de errores

### Reportar Bugs
Usar el sistema de Issues de GitHub incluyendo:
- 🐛 **Descripción** detallada
- 🔄 **Pasos** para reproducir
- 💻 **Entorno** (PHP, MySQL, OS)
- 📸 **Screenshots** si aplica

## 📜 Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

## 👨‍💻 Autor

**Portal de Noticias 24/7**
- 👤 **Autor**: Paloma
- 📧 **Email**: medios@cellcomweb.com.ar
- 🌐 **Empresa**: Cellcom Technology Medios S.A.
- 📍 **Ubicación**: Avellaneda, Santa Fe, Argentina

## 📞 Soporte

### Contacto
- 📧 **Email**: info@cellcomweb.com.ar
- 📞 **Teléfono**: +54 3482 549555
- 🏢 **Dirección**: Calle 9 NRO 539, Avellaneda, Santa Fe


### Recursos Adicionales
- 📚 **Demo del proyecto** (https://noti.ordenes.com.ar)
- 💬 **Usuario Admin**: (admin)
- 🐛 **clave**: (Admin123!)

---

<p align="center">
  <strong>Portal de Noticias 24/7</strong> - Desarrollado con ❤️ por Cellcom Technology
</p>

<p align="center">
  <a href="#portal-de-noticias-247">⬆️ Volver arriba</a>
</p>
