# 🔐 ATLAS Backend PG

## 📋 Descripción

**ATLAS Backend PG** es uno de los módulos del ecosistema **ATLAS**, desarrollado con **Laravel 12**. Este servicio expone APIs REST para administrar proyectos, entregables, rúbricas y procesos de aprobación.

### 🔧 Componentes Principales

#### Capa HTTP
- **Controladores API** (`app/Http/Controllers`): orquestan las solicitudes, delegando la lógica a servicios y modelos.
- **Middleware** (`app/Http/Middleware`): aplica autenticación, límites de acceso y trazabilidad de sesiones.
- **Form Requests** (`app/Http/Requests`): encapsulan reglas de validación y políticas de autorización por recurso.

#### Autenticación y Autorización
Este módulo se comunica con el módulo central de **ATLAS** para verificar los tókens de autenticación enviados por los usuarios, así como los roles y permisos que tienen asignados.

#### Modelos y Persistencia
- **Eloquent ORM** (`app/Models`): modelado de periodos académicos, grupos, proyectos y repositorios.
- **Migraciones** (`database/migrations`): versionado del esquema relacional (PostgreSQL por defecto).
- **Factories & Seeders** (`database/factories`, `database/seeders`): generación de datos de prueba y cargas iniciales.
- **Storage** (`storage/`): persistencia de archivos, reportes y documentación Swagger.

## 🚀 Inicio Rápido

### Prerrequisitos

- **PHP 8.4+** con extensiones `bcmath`, `intl`, `pcntl`, `pdo_pgsql`, `redis`, `zip`.
- **Composer 2.8+**
- **Node.js 20+** y **npm**
- **PostgreSQL 16+** (u otro motor soportado en `config/database.php`)

### Instalación local

```bash
# Clonar el repositorio
git clone https://github.com/Hannd15/atlas-backend-pg.git
cd atlas-backend-pg

# Dependencias PHP
composer install

# Dependencias front (Tailwind + Vite)
npm install

# Variables de entorno
cp .env.example .env
php artisan key:generate

# Migraciones y seeds
php artisan migrate --seed

# Generar documentación Swagger
php artisan l5-swagger:generate

# Compilar assets
npm run build
```

> **Tip:** para desarrollo puedes ejecutar `npm run dev` y `php artisan serve` (o `php artisan octane:start`) en paralelo.

## 🚢 Despliegue con Docker

El módulo incluye un `Dockerfile` multi-stage basado en **dunglas/frankenphp** que:

- Instala dependencias PHP en una etapa aislada de Composer.
- Construye assets con **Node 20 Alpine** y Vite.
- Empaqueta todo en una imagen ligera con **Octane + FrankenPHP** escuchando en `:8001`.
- Configura extensiones críticas (`pdo_pgsql`, `redis`, `pcntl`, `opcache`, etc.) y healthcheck en `/health`.

### Construir la imagen

```bash
docker build -t atlas-backend:latest .
```

### Ejemplo `docker-compose.yml`

```yaml
services:
  app:
    build: .
    image: atlas-backend:latest
    env_file: .env
    environment:
      APP_ENV: production
      APP_DEBUG: 'false'
      APP_URL: https://atlas.example.com
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: atlas
      DB_USERNAME: atlas
      DB_PASSWORD: secret
      PORT: 8001
    ports:
      - "8001:8001"
    volumes:
      - ./storage:/app/storage
    depends_on:
      db:
        condition: service_healthy

  db:
    image: postgres:16
    environment:
      POSTGRES_DB: atlas
      POSTGRES_USER: atlas
      POSTGRES_PASSWORD: secret
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U atlas"]
      interval: 10s
      timeout: 5s
      retries: 5
    volumes:
      - db-data:/var/lib/postgresql/data

volumes:
  db-data:
```

Después de levantar los contenedores:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan l5-swagger:generate
```

> **Nota:** monta `storage/` como volumen para conservar archivos, respaldos y documentación generada. Ajusta variables sensibles en `.env` o un gestor de secretos.

## 🔑 Ajustes de OAuth / Integraciones externas

El proyecto está listo para integrar OAuth2/Socialite. Configura tus credenciales en el `.env`:

```bash
GOOGLE_CLIENT_ID=tu_cliente
GOOGLE_CLIENT_SECRET=tu_secreto
GOOGLE_REDIRECT_URI=https://atlas.example.com/auth/google/callback
```

## 📖 Documentación API

- **Swagger UI**: `http://localhost:8001/api/documentation`
- Los archivos OpenAPI se generan en `storage/api-docs` tras ejecutar `php artisan l5-swagger:generate`.

## 🗂️ Estructura del Proyecto

```
atlas-backend-pg/
├── app/
│   ├── Console/Commands      # Tareas programadas y utilidades
│   ├── Http/Controllers      # Controladores API
│   ├── Http/Middleware       # Seguridad y contexto
│   ├── Http/Requests         # Validaciones
│   ├── Models                # Entidades Eloquent
│   ├── Providers             # Service Providers y bindings
│   └── Services              # Integraciones externas
├── bootstrap/                # Inicio de la app y binding de 
├── database/
│   ├── factories             # Fabricas de modelos
│   ├── migrations            # Migraciones
│   └── seeders               # Seeders
├── docs/                     # Documentación funcional
├── public/                   # Punto de entrada web / assets build
├── routes/                   # Rutas API, web y console
├── storage/                  # Logs, cache, Swagger, uploads
├── tests/                    # Suite PHPUnit
└── Dockerfile                # Imagen de despliegue (FrankenPHP + Octane)
```


---

<p align="center">
  Construido con ❤️ sobre <a href="https://laravel.com" target="_blank">Laravel</a>
</p>
