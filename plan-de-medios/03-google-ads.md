# Sistema Google Ads

Rol en el embudo: **captura de demanda activa** — personas y empresas que ya están buscando mobiliario de diseño, marcas específicas o mobiliario de proyecto. Es el canal de mayor intención de compra y debe protegerse primero (marca) antes de escalar a genérico.

## 1. Requisitos previos

- **Google Tag / Google Ads conversion tracking** instalado en el sitio, con eventos: envío de formulario de cotización, clic a WhatsApp, clic a llamada, "agendar visita a showroom", y compra si hay checkout online.
- **Google Merchant Center** con feed de productos (si se habilita catálogo online) para Shopping/Performance Max.
- **Landing pages por categoría** (sofás, sillas, mesas, mobiliario de terraza/exterior, iluminación, mobiliario contract) — nunca enviar tráfico pago solo al home.
- Vincular **Google Analytics 4** para medir comportamiento post-clic y alimentar audiencias de remarketing.

## 2. Estructura de campañas

| Campaña | Tipo | Objetivo | Ejemplos de keywords / segmentación |
|---|---|---|---|
| Marca | Search | Defender tráfico de marca, bajo CPC | "brainworks", "brainworks diseño", "brainworks muebles" |
| Genérico alta intención B2C | Search | Captar demanda de categoría | "muebles de diseño Santiago", "sofás de diseño", "muebles de terraza", "mobiliario exterior diseño", "muebles italianos Chile", "sillas de diseño europeas" |
| Proyectos / B2B contract | Search | Captar leads de proyecto | "mobiliario para hoteles", "mobiliario contract Chile", "muebles para restaurantes", "mobiliario alto tráfico", "proveedor mobiliario proyectos" |
| Shopping / Performance Max | Shopping + PMax | Vender catálogo específico, maximizar conversión con feed | Basado en feed de productos (título, categoría, atributos) |
| Display remarketing | Display | Recuperar visitantes que no convirtieron | Audiencia: visitantes del sitio 30–90 días, por categoría vista |
| Local (showroom) | Local campaign / Search con extensión de ubicación | Impulsar visitas al showroom El Golf/Vitacura | Búsquedas con intención "cerca de mí", extensión de ubicación en todas las campañas Search |

## 2.1 Nota — Campaña Inteligente vs. Búsqueda estándar

Durante la creación de la primera campaña, el asistente de Google mostró el flujo de **Campaña Inteligente (Smart Campaign)** (temas de palabras clave en vez de keywords con concordancia, puja 100% automática, sin negativas propias, targeting de ubicación sin ajuste de puja por zona). Es un producto más simple/automatizado que la campaña de Búsqueda estándar asumida en el resto de esta sección (fases de puja manual → Maximizar conversiones, negativas, concordancia de frase/exacta). Sirve para partir rápido, pero para el nivel de control que necesita el sistema completo (Marca / Genérico / Proyectos como campañas separadas, negativas propias, puja distinta por zona) conviene evaluar migrar a Búsqueda estándar una vez que haya volumen de conversión.

## 2.2 Segmentación geográfica — Campaña Genérico B2C

Hallazgo durante el setup: Brainworks ya ha recibido pedidos reales **desde Arica hasta Temuco** — la demanda real es nacional, no solo Santiago Oriente como asumía la persona B2C original (ver [00-audiencias-objetivos.md](00-audiencias-objetivos.md)).

- Usar segmentación por **regiones/ciudades**, no por radio alrededor de una dirección — un radio centrado en el showroom de Vitacura dejaría fuera toda la demanda ya comprobada fuera de Santiago.
- Regiones a incluir (cubriendo el rango confirmado): Arica y Parinacota, Tarapacá, Antofagasta, Atacama, Coquimbo, Valparaíso, Metropolitana, O'Higgins, Maule, Ñuble, Biobío, La Araucanía. Confirmar con el equipo si también ha habido pedidos más al sur (Los Ríos, Los Lagos, Aysén, Magallanes) — si el despacho nacional no tiene restricción real, puede ser más simple segmentar "Chile" completo.
- Limitación de Smart Campaign (ver 2.1): no permite pujar distinto por zona — todas las regiones compiten igual. En Búsqueda estándar sí se podría pujar más alto en Santiago Oriente (mayor probabilidad de visita a showroom) y más bajo en regiones lejanas (solo venta a distancia), otro argumento para migrar más adelante.

## 3. Negativas y control de calidad

- Negativas transversales: "gratis", "usado", "segunda mano", "ikea", "sodimac", "barato", "réplica", "trabajo" (para no atraer búsquedas de empleo).
- Concordancia: iniciar con frase/exacta en genérico y proyectos para controlar CPL; ampliar a amplia con Smart Bidding solo tras volumen de conversión suficiente (30+ conversiones/mes) para que el algoritmo optimice bien.
- Revisión semanal de términos de búsqueda las primeras 4–6 semanas.

## 4. Estrategias de puja (por madurez de cuenta)

1. **Fase 1 (mes 1):** CPC manual o Maximizar clics, para acumular datos de conversión.
2. **Fase 2 (mes 2 en adelante, con ≥15–30 conversiones/mes):** cambiar a **Maximizar conversiones** o **CPA objetivo**.
3. **Shopping/PMax:** ROAS objetivo una vez haya historial de ventas suficiente; antes, maximizar valor de conversión.

## 5. Creatividades / anuncios

- Search: mínimo 3 anuncios responsivos por grupo de anuncios, con extensiones de sitelink (Showroom, Catálogo, Marcas, Proyectos), llamada, ubicación y precio si aplica.
- Performance Max: set de imágenes de producto/ambiente + logo + video corto (puede reutilizar Reels de Instagram) + títulos y descripciones orientados a "diseño europeo", "interior y exterior", "showroom en Santiago".
- Mensaje diferenciado por campaña: B2C enfatiza diseño/estilo/showroom; B2B/proyectos enfatiza catálogo de marcas, plazos, asesoría técnica.

## 5.1 Especificaciones e ideas de imágenes (Performance Max / Display)

| Formato | Proporción | Tamaño mínimo | Cuántas subir |
|---|---|---|---|
| Horizontal | 1.91:1 | 1200×628 px | mínimo 3 (idealmente 5+) |
| Cuadrada | 1:1 | 1200×1200 px | mínimo 3 |
| Vertical | 4:5 | 1080×1350 px | recomendado 2-3 |
| Logo cuadrado | 1:1 | 1200×1200 px | 1 |
| Logo horizontal | 4:1 | 1200×300 px | 1 (opcional) |

Ideas de toma, con el formato al que mejor se ajusta cada una — una escena completa se lee bien ancha; un detalle o producto suelto se ve mejor en cuadrado o vertical, donde no sobra espacio vacío a los lados:

| # | Toma | Mejor formato | Por qué |
|---|---|---|---|
| 1 | Ambiente completo montado (living o terraza armada) | Horizontal | La escena entera se lee natural en panorámico — es la imagen "hero" |
| 2 | Vista amplia del showroom con varias piezas | Horizontal | El formato ancho deja entrar variedad de catálogo sin apretar |
| 3 | Fachada o interior panorámico del showroom | Horizontal | Un edificio o salón se fotografía naturalmente ancho; refuerza que existe un lugar físico (clave para campaña Local) |
| 4 | Persona usando el mueble, en plano abierto (ej. familia en la terraza) | Horizontal | Plano abierto deja espacio a los lados; mejor performance en Display/Discover que el producto solo |
| 5 | Antes/después de un proyecto real | Horizontal (panorámica o dividida) | Sirve también para el ángulo "Brainworks Proyectos" |
| 6 | Producto hero individual (una pieza sola, luz natural, fondo limpio) | Cuadrada | Mejor para Shopping/búsqueda de producto |
| 7 | Detalle de material/textura (tejido, madera, cerámica) | Cuadrada o vertical | Close-up, no necesita formato ancho; refuerza percepción de calidad |
| 8 | Marca en contexto (logo/etiqueta junto a la pieza) | Cuadrada | Toma cerrada; refuerza "importado", no genérico |
| 9 | Producto en ángulo 3/4 sobre fondo neutro, estilo catálogo | Cuadrada | Más "de venta" que el hero individual; útil si conectan Shopping/feed de producto |
| 10 | Rincón o vignette pequeño (2-3 piezas juntas en una esquina del showroom) | Cuadrada | Punto medio entre el producto solo y el ambiente completo; no necesita el ancho de una habitación entera |

El cuadrado es el formato más versátil de los tres (sirve para feed de Instagram, Display y Shopping) — si solo alcanza para fotografiar en un formato, priorizar este.

Tip de composición para horizontal: cada placement (banner de Display, miniatura de YouTube, Gmail) recorta distinto, así que mantener el sujeto principal centrado dentro del ~80% central de la imagen evita que le corten una esquina importante.

Evitar: fotos de stock genéricas, texto superpuesto pesado (se recorta distinto según placement), fondos con desorden/mala luz, o el logo solo como imagen principal.

Es la misma sesión de fotos planificada para el pilar "Producto & Showroom" de Instagram (ver [01-instagram.md](01-instagram.md)) — conviene coordinar una sola sesión que cubra ambos usos en vez de duplicar el costo de producción.

## 5.2 Copys de ejemplo — Campaña Genérico B2C

Set de referencia para el anuncio de búsqueda responsivo (RSA) de la campaña "Genérico alta intención B2C". Reutilizar como base y adaptar por grupo de anuncios (interior vs. terraza/exterior).

**Títulos (30 car. máx.):**

1. Muebles de Diseño Europeo
2. Brainworks Diseño Chile
3. Sofás y Sillas de Diseño
4. Muebles de Terraza Premium
5. +15 Marcas Europeas
6. Showroom en Vitacura
7. Interior y Exterior
8. Mobiliario de Diseño Italiano
9. Agenda tu Visita al Showroom
10. Cotiza tu Proyecto Hoy
11. Diseño Europeo para tu Hogar
12. Muebles Exterior de Diseño
13. Calidad Europea, Stock Chile
14. Vondom, Magis y Más
15. Envíos a Todo Chile

**Descripciones (90 car. máx.; las primeras dos también caben en formularios con límite de 60/90):**

1. Mobiliario de diseño europeo para interior y exterior. Visita el showroom en Vitacura. (86)
2. Más de 15 marcas europeas en un solo lugar. Cotiza tu proyecto sin costo. (73)
3. Mobiliario para proyectos: hoteles, restaurantes y residencias. Cotiza aquí. (76)
4. Importación directa de Italia, España y Holanda. Calidad garantizada. (69)
5. Diseño de interior y exterior en un solo lugar. (47)
6. Piezas de diseño para tu casa, oficina o terraza. (49)
7. Muebles de diseño con stock disponible en Chile. (48)
8. Agenda tu visita al showroom y conoce el catálogo completo. (59)
9. Sofás, sillas, mesas y mobiliario de exterior de diseño. (56)
10. Encuentra piezas únicas de Vondom, Magis, BD Barcelona y más. (61)
11. Envíos a todo Chile y hasta 12 cuotas sin interés. (50)
12. Asesoría de diseño para proyectos residenciales y comerciales. (62)

## 5.3 Términos de búsqueda — Campaña Genérico B2C

Lista ampliada para el paso "Elige los términos que buscan tus clientes en Google" (o para armar los grupos de anuncios de una campaña estándar). Agrupada por subtema para elegir según el ángulo del grupo de anuncios; en el asistente simplificado se ingresan como frases sueltas.

**Muebles de diseño (general):** muebles de diseño · muebles de diseño Santiago · tienda de muebles de diseño · muebles de diseño europeos · muebles importados Chile · muebles italianos Chile · mobiliario de diseño

**Living — sofás y sillas:** sofás de diseño · sillas de diseño · sillas de diseño europeas · sillones de diseño · muebles para living

**Comedor:** mesas de diseño · mesas de comedor de diseño

**Terraza y exterior:** muebles de terraza · muebles de exterior · mobiliario exterior diseño · muebles para terraza y jardín · sillas de exterior diseño

**Iluminación:** lámparas de diseño · iluminación de diseño

**Showroom / marca:** showroom de muebles Santiago · tienda de diseño Vitacura

Revisar cualquier término "sugerido" que el asistente saque automáticamente del sitio antes de aceptarlo (puede traer búsquedas genéricas de decoración sin intención de compra) y excluir siempre la lista de negativas ya definida en la sección 3. Usar concordancia de frase o exacta al empezar, según lo indicado en "Estrategias de puja".

Notas: sin signos de exclamación en títulos (rechazados por política de Google); no fijar ("pin") títulos salvo el del nombre de marca si se quiere presencia constante — dejar el resto libre para que el algoritmo rote combinaciones. Al elegir cuáles cargar, mezclar ángulos distintos (producto, marca/catálogo, CTA, beneficio) en vez de repetir la misma idea — mejora el "Ad Strength".

## 6. KPIs del canal

- CPL (costo por lead) por campaña, comparado contra el valor promedio de ticket B2C vs. proyecto B2B.
- Tasa de conversión de la landing (clics → formulario/WhatsApp).
- Share of impression en campaña de marca (debe mantenerse alto, >80–90%) para evitar fuga a competencia en búsqueda de marca propia.
- ROAS en Shopping/PMax si hay venta online directa.
- Costo por visita a showroom agendada (campaña local).
