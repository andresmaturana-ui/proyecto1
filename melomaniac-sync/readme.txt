=== Melomaniac Sync ===
Contributors: melomaniac
Tags: woocommerce, vinyl, music, barcode, musicbrainz
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Escaneá el código de barra de un disco y Melomaniac Sync completa la ficha del producto de WooCommerce con sus datos musicales.

== Description ==

Melomaniac Sync está hecho para disquerías que venden vinilos, CDs y cassettes en su propia tienda WooCommerce.

Escaneás el código de barra del disco con un lector USB o con la cámara, el plugin lo busca en MusicBrainz y crea el producto como borrador con artista, título, sello, año, formato, país de prensaje, número de catálogo, lista de canciones y portada.

Si el disco no está en MusicBrainz, el plugin abre un formulario para cargarlo a mano, con enlaces de referencia para buscar los datos por tu cuenta.

= Fuentes de datos =

El plugin consulta dos servicios públicos, los dos de la MetaBrainz Foundation:

* **MusicBrainz** (musicbrainz.org) para los datos del lanzamiento. Lectura sin autenticación, respetando el límite de una petición por segundo y enviando un User-Agent identificatorio con el contacto de la tienda.
* **Cover Art Archive** (coverartarchive.org) para la portada.

No se envía ningún dato de la tienda ni de sus clientes a esos servicios: solo el código de barra que estás buscando.

El plugin incluye enlaces de búsqueda manual hacia Discogs y Google. Son enlaces que abrís vos en una pestaña nueva: el plugin nunca hace peticiones automáticas ni copia datos de esos sitios.

== Installation ==

1. Subí la carpeta `melomaniac-sync` a `/wp-content/plugins/`.
2. Activá el plugin desde el menú Plugins.
3. Abrí **Melomaniac Sync > Escanear disco**.

WooCommerce tiene que estar activo.

== Frequently Asked Questions ==

= ¿Necesito un lector de código de barra? =

No. Podés tipear el código a mano, o usar la cámara del dispositivo en navegadores basados en Chromium. Un lector USB funciona sin configuración: se comporta como un teclado.

= ¿El escaneo con la cámara funciona en cualquier navegador? =

Usa la API nativa del navegador (BarcodeDetector), disponible en Chrome, Edge y Android. En Safari y Firefox el botón avisa que no está disponible y podés usar un lector USB o tipear el código.

= ¿Los productos se publican solos? =

No. Se crean siempre como borrador para que los revises, les pongas precio y stock antes de publicarlos.

= ¿Qué pasa si escaneo un disco que ya cargué? =

El plugin lo detecta por SKU y por identificador de MusicBrainz, y te muestra un enlace al producto que ya existe en lugar de duplicarlo.

== Changelog ==

= 1.0.0 =
* Primera versión: escaneo individual por código de barra, búsqueda en MusicBrainz, portada desde Cover Art Archive, creación del producto como borrador y formulario de carga manual.
