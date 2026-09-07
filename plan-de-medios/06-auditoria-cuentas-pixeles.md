# Auditoría de cuentas y píxeles — cómo reunir la información

Antes de poder unificar Business Managers, cuentas de Ads y píxeles duplicados, necesitamos un inventario completo de lo que existe hoy. Esto suele desordenarse porque distintas agencias/freelancers van creando cuentas nuevas en vez de reusar las existentes.

**Regla general: nunca compartir contraseñas.** Donde se pida "dar acceso", se usa la función de agregar usuario/partner de cada plataforma (con el correo de quien vaya a auditar), no las credenciales de la cuenta.

La guía paso a paso completa, más una planilla para ir registrando cada activo encontrado, está en [`presentacion/Brainworks-Inventario-Cuentas-y-Pixeles.xlsx`](presentacion/Brainworks-Inventario-Cuentas-y-Pixeles.xlsx) — tiene dos pestañas:

1. **Instrucciones** — paso a paso por plataforma (Meta, Google, LinkedIn, sitio web, organización) con las rutas de clics exactas para encontrar cada ID.
2. **Inventario de cuentas** — tabla para registrar cada Business Manager, cuenta de Ads, píxel, página, propiedad de GA4, contenedor de GTM, etc. que se vaya encontrando, con quién lo administra hoy y su estado (activo / duplicado / huérfano / dar de baja).

## Resumen de qué se necesita, por plataforma

- **Meta (Business Manager):** todos los BM asociados a la empresa, IDs de cuentas publicitarias, IDs de píxel (Events Manager), páginas de Facebook e Instagram conectadas.
- **Google:** cuentas de Google Ads (y si hay una MCC que las agrupe), propiedades de GA4 (Measurement ID), contenedores de Google Tag Manager, cuenta de Merchant Center, fichas de Google Business Profile (revisar duplicados), Search Console.
- **LinkedIn:** páginas de empresa (revisar duplicadas), cuenta de Campaign Manager, Insight Tag ID y su estado de actividad.
- **Sitio web:** acceso al CMS o, si no está disponible, revisión del código fuente público para detectar qué píxeles están instalados hoy; acceso a hosting/DNS para resolver el subdominio viejo `2024.brainworks.cl` (ver [05-arquitectura-web.md](05-arquitectura-web.md)).
- **Organización:** definir un correo corporativo como dueño de todas las cuentas (no una persona), y registrar quién gestiona cada plataforma hoy.

Una vez completado el inventario, el siguiente paso es el plan de consolidación: qué cuentas se quedan como "fuente única de verdad" por plataforma, cuáles se dan de baja, y cómo se migran los datos históricos (campañas, audiencias guardadas) antes de cerrar las duplicadas.
