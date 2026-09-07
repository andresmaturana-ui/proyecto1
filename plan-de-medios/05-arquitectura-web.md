# Arquitectura de brainworks.cl para los 3 públicos

Este documento complementa el plan de medios: define cómo debería organizarse el sitio para que el tráfico que generan Instagram, LinkedIn y Google Ads (ver [00-audiencias-objetivos.md](00-audiencias-objetivos.md)) aterrice en la página correcta y convierta, en vez de perderse en un catálogo genérico.

## 1. Qué existe hoy (relevado vía búsqueda pública, sin acceso directo al sitio)

| Página encontrada | Función actual |
|---|---|
| `brainworks.cl/` (Landing-2025) | Home |
| `brainworks.cl/shop/` y `2024.brainworks.cl/shop/` | Catálogo / e-commerce — **hay un subdominio viejo (`2024.brainworks.cl`) todavía indexado en paralelo al sitio actual** |
| `/product/…`, `/product-category/mesas/` | Fichas de producto y categorías |
| `/marcas/` y `/brand/` | Página de marcas representadas |
| `/showroom/` | Info de showroom, visita con cita por WhatsApp |
| `/muebles-de-terraza/` | Landing de categoría exterior |
| `/descargas/` | Descargas (catálogos PDF) |
| `/contactos/` | Contacto |
| "Brainworks Proyectos" | Servicio de oficina de diseño (interiorismo, especificación, renders) — existe pero no aparece como sección propia y jerarquizada |

Catálogo: +300 productos de 9 marcas (Vondom, BD Barcelona, Plust, Scab, OMP, Infiniti, Magis, Tramontina + colección propia BW).

**Fricciones detectadas:**
- Dos contactos distintos (`tienda@brainworks.cl` / `info@brainworks.cl`, dos teléfonos) sin que quede claro cuál usar según el tipo de consulta.
- El subdominio `2024.brainworks.cl/shop/` sigue indexado junto al sitio vigente — confunde a usuarios y diluye el "quality score" de las campañas de Google Ads que apunten a `/shop/`.
- Direcciones de showroom inconsistentes entre fuentes históricas (Barrio Italia, La Reina, El Golf/Vitacura) — el sitio debe mostrar **una sola dirección vigente** en todas partes (header, footer, página de showroom, Google Business Profile). Confirmado en vivo durante la auditoría: el Business Portfolio de Meta muestra "cristal de abelli. 3021, santiago... 77750500" (código postal de 8 dígitos, no válido en Chile) y la extensión de ubicación de Google Ads (tomada de Google Business Profile) muestra "Cristal de Abellia 3021, Las Condes" — ni la calle ni la comuna coinciden entre ambas fuentes. Corregir la ficha de Google Business Profile con la dirección real antes de activar extensiones de ubicación en campañas.
- Igual patrón con el teléfono: han aparecido +56 9 7307 5604 (showroom), +56 2 243 80 550 (tienda) y +56 9 9527 4895 (Business Portfolio de Meta) — definir uno solo como oficial antes de cargarlo en el botón de llamada de Google Ads.
- "Brainworks Proyectos" (el ángulo B2B) no tiene visibilidad de primer nivel — hoy el sitio se lee principalmente como tienda retail.

## 2. El problema de fondo

El sitio tiene **una sola puerta de entrada** (el catálogo de tienda) para **tres compradores que deciden distinto**:

- El comprador final quiere ver, comparar precio y agendar una visita.
- El arquitecto/interiorista quiere fichas técnicas, condiciones trade y portafolio de proyectos — no quiere navegar un carro de compras.
- El comprador de proyecto contract quiere cotizar volumen y ver garantías/plazos — tampoco compra "a la unidad".

Mandar tráfico pago de LinkedIn o de Google Ads B2B a una ficha de producto de e-commerce es la fuga de conversión más probable del sistema completo.

## 3. Arquitectura propuesta: un sitio, tres recorridos

```
Home
 ├─ ¿Para tu casa?  ───────────► TIENDA          (Persona I · B2C)
 ├─ ¿Eres arquitecto/a o        ► PROYECTOS       (Persona II · B2B trade)
 │   diseñador/a?
 └─ ¿Mobiliario para un         ► CONTRACT        (Persona III · B2B proyecto)
     hotel, restaurant, oficina?
```

### Home
- Tres CTAs de segmentación inmediatamente bajo el hero (no solo "Ver catálogo"): **"Compra para tu casa" / "Soy arquitecto o interiorista" / "Tengo un proyecto (hotel, restaurante, oficina)"**. Cada uno lleva a su recorrido.
- El resto del home puede seguir siendo vitrina de marca (showroom, marcas, proyectos destacados) — pero ya no como única puerta.

### Recorrido 1 — Tienda (Persona I, B2C)
- Navegación por **ambiente** (Living, Comedor, Dormitorio, Terraza/Exterior, Iluminación, Accesorios) — hoy la única landing de categoría visible es "muebles de terraza"; falta el resto de ambientes como landings propias para poder mandarles tráfico de Google Ads sin caer siempre en el home.
- Filtro secundario por marca (Vondom, Magis, BD Barcelona, etc.), reutilizando `/marcas/`.
- Ficha de producto con dos CTA claros: **"Comprar"** (si hay stock/checkout) y **"Agenda visita al showroom"** — no forzar todo a compra online, mucha decisión de diseño se cierra viendo la pieza en persona.
- Showroom con dirección única, horario y botón de WhatsApp para agendar (ya existe, mantener y estandarizar).

### Recorrido 2 — Proyectos / Trade (Persona II, B2B arquitectos)
- Landing propia "Para Arquitectos y Diseñadores" (subir "Brainworks Proyectos" a la navegación principal, no dejarlo implícito).
- Contenido: portafolio de proyectos con foto + ficha (m², marcas usadas), condiciones trade/comisión, proceso de trabajo (especificación → muestras → plazos), fichas técnicas descargables por producto.
- Conversión: formulario "Regístrate como profesional" (nombre, estudio, RUT/empresa) que entrega acceso a `/descargas/` con catálogo técnico en PDF — es el mismo lead magnet que alimenta LinkedIn Ads (ver [02-linkedin.md](02-linkedin.md)).
- Nunca mandar a este visitante al flujo de carro de compra unitario.

### Recorrido 3 — Contract / Proyectos de alto tráfico (Persona III)
- Landing separada de la de arquitectos: aquí el comprador no diseña, **compra volumen** para un hotel, restaurante u oficina.
- Contenido: casos de hotelería/restaurantes, garantía y durabilidad de materiales de exterior, capacidad de producción/plazos, no precios unitarios.
- Conversión: formulario de **cotización de proyecto** (tipo de proyecto, m² o N° de piezas, plazo estimado) — no "agregar al carro". Este es el formulario que debe recibir el tráfico de la campaña Google Search "Proyectos / B2B" y de LinkedIn Lead Gen.

## 4. Reglas de tracking (para que esto se pueda medir)

Cada recorrido necesita su propio evento de conversión en GA4/CRM, ya anticipado en el [tablero de KPIs](04-presupuesto-kpis-calendario.md):

| Recorrido | Evento de conversión | Alimenta |
|---|---|---|
| Tienda | `compra` / `cotizacion_producto` / `visita_showroom_agendada` | Google Ads Shopping/PMax, Meta Ads conversión |
| Proyectos (trade) | `registro_profesional` / `descarga_catalogo_tecnico` | LinkedIn Lead Gen, Google Ads B2B |
| Contract | `cotizacion_proyecto` | LinkedIn Lead Gen, Google Ads B2B, Local |

Sin esta separación, todos los leads caen en un solo balde y no se puede saber qué canal está trayendo compradores reales vs. arquitectos vs. hoteles — que es justamente lo que el plan de medios necesita reportar mes a mes.

## 5. Prioridad de implementación

1. **Fijar el problema de indexación** del subdominio `2024.brainworks.cl` (redirect 301 al sitio vigente) y unificar dirección/contacto en todo el sitio — bajo esfuerzo, impacto inmediato en SEO y en calidad de campañas.
2. **Landing de Proyectos/Trade** con formulario de registro profesional — es el activo que necesita LinkedIn desde el mes 1.
3. **Landing de Contract** con formulario de cotización de proyecto — necesaria antes de lanzar la campaña Search "Proyectos/B2B".
4. **Landings por ambiente** en la tienda (Living, Comedor, Dormitorio, Iluminación) además de la de terraza ya existente — para dejar de mandar tráfico pago genérico al home.
5. Segmentación de 3 CTAs en el home — ajuste de home, último paso una vez existan las 3 landings de destino.
