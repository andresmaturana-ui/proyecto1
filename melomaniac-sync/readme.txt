=== Melomaniac Sync ===
Contributors: melomaniac
Tags: woocommerce, vinyl, music, barcode, musicbrainz
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Escanea el código de barra de un disco y Melomaniac Sync completa la ficha del producto de WooCommerce con sus datos musicales.

== Description ==

Melomaniac Sync está hecho para disquerías que venden vinilos, CDs y cassettes en su propia tienda WooCommerce.

Escaneas el código de barra del disco con un lector USB o con la cámara, el plugin lo busca en MusicBrainz y crea el producto como borrador con artista, título, sello, año, formato, país de prensaje, número de catálogo, lista de canciones y portada.

Si el disco no está en MusicBrainz, el plugin abre un formulario para cargarlo a mano, con enlaces de referencia para buscar los datos por tu cuenta.

= Fuentes de datos =

El plugin consulta dos servicios públicos, los dos de la MetaBrainz Foundation:

* **MusicBrainz** (musicbrainz.org) para los datos del lanzamiento. Lectura sin autenticación, respetando el límite de una petición por segundo y enviando un User-Agent identificatorio con el contacto de la tienda.
* **Cover Art Archive** (coverartarchive.org) para la portada.

No se envía ningún dato de la tienda ni de sus clientes a esos servicios: solo el código de barra que estás buscando.

El plugin incluye enlaces de búsqueda manual hacia Discogs y Google. Son enlaces que abres tú en una pestaña nueva: el plugin nunca hace peticiones automáticas ni copia datos de esos sitios.

== Installation ==

1. Sube la carpeta `melomaniac-sync` a `/wp-content/plugins/`.
2. Activa el plugin desde el menú Plugins.
3. Abre **Melomaniac Sync > Escanear disco**.

WooCommerce tiene que estar activo.

== Frequently Asked Questions ==

= ¿Necesito un lector de código de barra? =

No. Puedes escribir el código a mano, o usar la cámara del dispositivo. Un lector USB funciona sin configuración: se comporta como un teclado.

= ¿El escaneo con la cámara funciona en cualquier navegador? =

Sí, tanto en el celular como en el computador. Usa la API nativa del navegador (BarcodeDetector) cuando está disponible (Chrome, Edge y Samsung Internet en Android), y si no lo está —Safari en iPhone o Mac, o un navegador de escritorio— pasa a un decodificador propio incluido en el plugin. La página tiene que estar en HTTPS: sin eso, ningún navegador deja usar la cámara.

= ¿Los productos se publican solos? =

No. Se crean siempre como borrador para que los revises, les pongas precio y stock antes de publicarlos.

= ¿Qué pasa si escaneo un disco que ya cargué? =

El plugin lo detecta por SKU y por identificador de MusicBrainz, y te muestra un enlace al producto que ya existe en lugar de duplicarlo.

= ¿Cómo funciona la carga masiva? =

Se procesa en segundo plano con Action Scheduler (la misma librería que usa WooCommerce para sus propias tareas programadas), un código de barra a la vez y espaciados entre sí, así que no hace falta dejar la pestaña abierta. Necesita que el cron de WordPress esté funcionando con normalidad, como cualquier otra tarea programada del sitio. Se puede cargar pegando una lista o subiendo un CSV con columnas código, precio y cantidad (hay una plantilla para descargar en la misma pantalla). Cuando un código tiene más de una edición posible, la carga se detiene en ese disco y muestra las opciones para que elijas cuál es, igual que al escanear uno solo.

== Changelog ==

= 2.1.1 =
* La pantalla de cargar un disco a mano en la app ahora también muestra los enlaces para buscarlo en Discogs, Google o MusicBrainz, igual que ya pasaba en el formulario manual de wp-admin. Son enlaces para abrir y copiar los datos a mano: el plugin no consulta esos sitios por su cuenta.

= 2.1.0 =
* La carga masiva ahora también acepta un archivo CSV (columnas código, precio y cantidad), con una plantilla para descargar en la misma pantalla. El precio y la cantidad del archivo reemplazan a los elegidos en el formulario solo para esa fila; lo demás (categorías, etiquetas) se aplica igual a todos.
* Cuando un código de barra tiene más de una edición posible, la carga masiva ya no elige la primera sola: deja el disco esperando y muestra las opciones en la pantalla de la carga para elegir cuál es, el mismo paso que ya existía al escanear uno solo.
* En la app, "Ajustes" y "Cerrar sesión" pasan a ser dos botones siempre visibles junto al nombre de la tienda, en vez de estar escondidos detrás de un menú.

= 2.0.0 =
* Agrega la carga masiva: pega una lista de códigos de barra (uno por línea) en Melomaniac Sync → Carga masiva, y el plugin los busca y crea uno por uno en segundo plano, respetando el límite de MusicBrainz, sin que tengas que dejar la pestaña abierta ni esperar. La pantalla se actualiza sola mostrando qué se creó, qué no se encontró y qué ya existía; lo que no se encuentra queda disponible para cargarlo a mano después.

= 1.9.0 =
* La cámara para escanear ahora funciona en cualquier navegador con HTTPS, incluyendo Safari en iPhone y Mac y los navegadores de escritorio: cuando el navegador no trae BarcodeDetector, se usa un decodificador propio (ZXing) incluido en el plugin.
* La app suma un menú (el ícono ⋮ junto al nombre de la tienda) con el usuario conectado, un enlace directo a Ajustes y el botón para cerrar sesión.

= 1.8.2 =
* En la app, cuando el navegador no puede usar la cámara (porque la página no está en HTTPS, o porque el navegador no lo soporta) ahora lo avisa de inmediato y esconde el botón, en vez de dejar que lo toques y no veas que pasa nada.

= 1.8.1 =
* En la app, la pantalla de confirmar disco ahora muestra la portada que se va a usar (o avisa que no encontró ninguna) y deja tomar una foto o subir una imagen ahí mismo, sin tener que pasar por "corregir a mano" solo para agregar una portada.

= 1.8.0 =
* Suma una app web instalable, servida directo desde el plugin en /melomaniac-app/: se abre desde el navegador del teléfono, se agrega a la pantalla de inicio y permite escanear, revisar y crear productos sin entrar a wp-admin. Se activa y desactiva desde Ajustes.

= 1.7.1 =
* En la ficha del producto, la tabla con los datos del disco (artista, sello, año, formato, país de prensaje, número de catálogo) ahora se muestra bajo el precio, y el resumen que antes iba ahí pasa a mostrarse donde antes estaba esa tabla.

= 1.7.0 =
* Agrega una API REST (melomaniac-sync/v1) y autenticación con contraseñas de aplicación de WordPress, para que una app o herramienta externa pueda buscar discos y crear productos sin pasar por wp-admin.

= 1.0.0 =
* Primera versión: escaneo individual por código de barra, búsqueda en MusicBrainz, portada desde Cover Art Archive, creación del producto como borrador y formulario de carga manual.
