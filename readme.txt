7C Mautic–WABA Bridge (Fase 1)
--------------------------------
- Solo setup + Settings
- Menú: 7C Mautic–WABA -> Settings
- Próximas fases: endpoint de ingest (HMAC) y generador de líneas

Mapa de archivos
----------------
7c-mwb.php
  -> Bootstrap del plugin: define constantes, activa autoloader, inicia Plugin.

src/Plugin.php
  -> Orquestador principal: registra menús, ajustes y carga de assets.

src/Admin/Menu.php
  -> Controla los menús en el admin (add_menu_page).
  -> Llama a la vista de Settings.

src/Settings/Keys.php
  -> Define las constantes/keys de todas las opciones del plugin.

src/Settings/Defaults.php
  -> Valores por defecto de las opciones y ensure_defaults() en activación.

src/Settings/Sanitizer.php
  -> Lógica de sanitización/validación al guardar opciones.

src/Settings/Registry.php
  -> Registro de secciones y campos de Settings API.
  -> Render de inputs (text, password, select, textarea).

src/Util/Arr.php
  -> Helper para acceder a arrays de forma segura.

src/Util/View.php
  -> Helper para renderizar vistas desde /views.

views/admin/settings-page.php
  -> Vista de la página de Settings (usa Registry para inputs).
  -> Incluye resumen de valores configurados.

assets/admin.css
  -> Estilos mínimos para la página de Settings.

assets/admin.js
  -> JS para el admin (vacío por ahora, reservado para futuro).
