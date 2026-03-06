# Sistema de alquiler de canchas

Sistema web en PHP + MySQL para gestión de reservas, clientes, reportes y administración.

## Requisitos

- Docker y Docker Compose

## Puesta en marcha local (lista para demo)

1. Copiá variables de entorno:

```bash
cp .env.example .env
```

2. Levantá los servicios:

```bash
docker compose up -d --build
```

3. Abrí la app:

- Front/admin: http://localhost:8080
- Base MySQL expuesta en host: `localhost:3307`

> La base de datos se inicializa automáticamente con `alquiler_canchas.sql` en el primer arranque.

## Variables de entorno importantes

La aplicación ahora toma su configuración desde variables de entorno:

- `APP_ENV` (`development|production`)
- `APP_DEBUG` (`0|1`)
- `TZ`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `SESSION_COOKIE_SECURE` (`1` recomendado en HTTPS)

## Deploy en la nube (recomendado: Render)

### Opción A: usando Docker (simple)

1. Subí este repo a GitHub.
2. En Render, creá un **Web Service** desde el repo:
   - Runtime: Docker
   - Branch: `main`
   - Port: `80`
3. Creá además un **MySQL** administrado (o usá proveedor externo).
4. Configurá variables de entorno del Web Service:
   - `APP_ENV=production`
   - `APP_DEBUG=0`
   - `SESSION_COOKIE_SECURE=1`
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (del MySQL de nube)
5. Importá `alquiler_canchas.sql` en la instancia MySQL (una sola vez).
6. Deploy y validación final desde URL pública de Render.

### Opción B: Railway / Fly.io / VPS

- Usar el mismo `Dockerfile` para el servicio PHP.
- Provisionar MySQL administrado.
- Cargar las mismas variables de entorno.
- Importar el SQL inicial una única vez.

## Seguridad mínima para producción

- Usar HTTPS obligatorio.
- `APP_DEBUG=0`.
- `SESSION_COOKIE_SECURE=1`.
- Contraseñas robustas para DB.
- Restringir acceso público a la base de datos (solo red privada).

## Comandos útiles

```bash
# ver logs
docker compose logs -f app

# reiniciar
docker compose restart

# bajar entorno
docker compose down
```
