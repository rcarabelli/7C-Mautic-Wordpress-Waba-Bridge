# 7C Mautic–WABA Bridge (Fase 1)

Puente base entre **Mautic** y **WhatsApp Business API (WABA / Meta)**.  
En esta fase se enfoca en **Setup + Settings**, rutas REST **scaffold** (preparadas) y utilitarios para estandarizar la **ingesta** de mensajes (vía webhook) con **HMAC**.

---

## Datos del proyecto / autor
- **Plugin**: 7C Mautic–WABA Bridge  
- **Versión**: 0.1.0 (Fase 1)  
- **Autor**: Joe Doe Corp — **Joe Doe**  
- **Contacto**: <joe.doe@example.com>  
- **Web**: https://example.com  
- **Requiere**: WordPress ≥ 6.0, **PHP ≥ 8.1**  
- **Licencia**: GPL-2.0-or-later (sugerido)

> **Nota**: Si el proyecto es privado, documenta aquí el repositorio (Git) y el flujo de despliegue (cPanel/SSH/CI).

---

## Resumen (Fase 1)
- **Objetivo**: habilitar configuración segura para un puente Mautic → WABA y preparar endpoints REST.  
- **Alcance**:
  - Pantalla de **Settings** (admin) con claves, defaults y sanitización.
  - **Installer** que asegura valores por defecto en la activación y upgrades.
  - **Rutas REST scaffold** para **ingesta** (payload tipo Mautic → WABA) y **status** (WABA → WP).  
    En Fase 1 se limitan a **validación, logging y respuesta estándar** (sin despachar a BSP todavía).

**Próximas fases**:
1. **Ingest endpoint HMAC**: enrutar payload hacia BSP/Meta o cola interna.  
2. **Generador de líneas**: UI para convertir templates de WABA en instrucciones de webhook Mautic.  
3. Persistencia estructurada de eventos de **status** y dashboard básico.

---

## Instalación rápida
1. Copia la carpeta del plugin en `wp-content/plugins/7c-mwb/`.  
2. Activa **7C Mautic–WABA Bridge** en *Plugins* → *Activar*.  
3. Ve a **7C Mautic–WABA → Settings** y completa:
   - **HMAC Secret** (clave para verificar firmas entrantes).
   - **Slug de ingesta** (p. ej. `waba-webhook`).
   - **Slug de status** (p. ej. `waba-status`).
   - **Teléfono predeterminado** (placeholder para pruebas, p. ej. `1999999999`).
   - **Idioma por defecto** (p. ej. `en_US`, `es_ES`, `es_MX`).
   - **Modo Debug** (si se requiere logging detallado).

---

## Menú
- **7C Mautic–WABA → Settings**: pantalla única con todas las opciones de la fase 1 y un resumen de configuración cargada.

---

## Endpoints REST (scaffold)

> Las rutas REST pueden habilitarse/deshabilitarse por Settings.  
> En Fase 1 registran, validan HMAC y **logean**.

### 1) Ingesta (Mautic → WP)
- **Método**: `POST`  
- **Ruta**: `/wp-json/7c-mwb/v1/<INGEST_SLUG>`  
  (por defecto `/wp-json/7c-mwb/v1/waba-webhook`)  
- **Headers requeridos**:
  - `Content-Type: application/json`
  - `X-7C-Signature: <base64(hmac_sha256(raw_body, HMAC_SECRET))>`

**Body (JSON) sugerido:**
```json
{
  "to": "1999999999",
  "name": "hello_world",
  "lang": "en_US",
  "vars": ["Nombre"],
  "components": [],
  "meta": {
    "source": "mautic",
    "campaign": "encuesta_wa01a"
  }
}
```

**Respuesta:**
```bash
{ "ok": true, "received": true }
```

**Ejemplo (firma HMAC con openssl):**
```bash
SECRET='REEMPLAZA_CON_TU_HMAC_SECRET'
BODY='{"to":"1999999999","name":"hello_world","lang":"en_US","vars":["Nombre"]}'
SIG=$(printf '%s' "$BODY" \
  | openssl dgst -sha256 -mac HMAC -macopt key:"$SECRET" -binary \
  | openssl base64)

curl -sS -X POST \
  'https://tu-dominio.tld/wp-json/7c-mwb/v1/waba-webhook' \
  -H 'Content-Type: application/json' \
  -H "X-7C-Signature: $SIG" \
  -d "$BODY"
```

### 2) Status (WABA → WP)
- **Método**: POST
- **Ruta**: /wp-json/7c-mwb/v1/<STATUS_SLUG>
(por defecto /wp-json/7c-mwb/v1/waba-status)
- **Body**: espejo del JSON de statuses de WABA/Meta (id, status, timestamp, conversation, pricing, etc.).
- **Comportamiento**: en Fase 1 se logea con prefijo [WABA STATUS RAW] (para auditoría), y responde:

```json
{ "ok": true }
```

**Tip**: si usarás ambos webhooks en el mismo dominio, configura rutas con slugs claros para separarlos y facilitar la depuración.

## Seguridad
- **Firmas HMAC**: toda ingesta exige X-7C-Signature generada con HMAC_SHA256 del body crudo y secreto configurado.
- **Sanitización**: las opciones se validan en src/Settings/Sanitizer.php.
-  **Roles**: el menú solo aparece a manage_options.

## Logging & Debug
- Prefijo de logs (PHP): [7C-MWB].
- En Fase 1, el status webhook imprime el JSON crudo con el prefijo [WABA STATUS RAW].
- Activa WP_DEBUG_LOG para enviar a wp-content/debug.log.

## Mapa de archivos (resumen)
```text
/wp-content/plugins/7c-mwb
├── 7c-mwb.php
├── assets
│   ├── admin.css
│   └── admin.js
├── includes
│   └── class-rest-ingest.php    (legacy - migrar a src/REST)
├── readme.md
└── src
    ├── Admin
    │   ├── Menu.php
    │   ├── SettingsPage.php
    │   └── TemplateHelper.php
    ├── Plugin.php
    ├── REST
    │   ├── Ingest_Controller.php
    │   └── Waba_Status_Webhook.php
    ├── Settings
    │   ├── Defaults.php
    │   ├── Keys.php
    │   ├── Registry.php
    │   └── Sanitizer.php
    ├── Setup
    │   └── Installer.php
    ├── Util
    │   ├── Arr.php
    │   └── View.php
    └── views
        └── admin
            └── settings-page.php
```
