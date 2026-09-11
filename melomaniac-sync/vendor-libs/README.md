# vendor-libs

Librerías de terceros que se distribuyen dentro del ZIP del plugin, para que se
pueda instalar desde WordPress sin paso de build.

- `freemius/` — SDK de licenciamiento (Fase 2).
- `zxing/` — `@zxing/library` (build UMD, minificada, sin modificar), usada por
  `assets/js/camera-scanner.js` como respaldo cuando el navegador no trae la
  API nativa BarcodeDetector — Safari (iPhone y Mac) y los navegadores de
  escritorio en general, que nunca la implementaron.

`composer.json` en la raíz existe solo para las herramientas de desarrollo
(PHPCS / WPCS). Nada de `vendor/` se distribuye.
