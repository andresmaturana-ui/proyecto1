# Disquería App — iOS

App nativa en SwiftUI para iOS, convertida a partir del plugin de WordPress
`disqueria-app-plugin` (carpeta original: `app/index.html` + `app/support.js` +
`disqueria-app-plugin.php`). Cumple la misma función que la mini-app web que el
plugin servía en `/app`: escanear el código de barras de un disco, buscar sus
datos en Discogs, fotografiarlo, calificar su estado y publicarlo como producto
de WooCommerce — todo hablando directamente con los mismos endpoints REST que
ya expone el plugin (`disqueria/v1/...`).

**El plugin de WordPress (`disqueria-app-plugin.php`) sigue siendo necesario.**
Esta app es el reemplazo nativo de la mini-app web (`app/index.html`), pero
sigue consumiendo los endpoints que ese plugin registra en el WordPress/Woo del
usuario. No se tocó el plugin.

## Qué cambia respecto a la versión web

| Web (plugin) | iOS nativo |
|---|---|
| Framework de componentes propio (`x-dc`/`DCLogic`) | SwiftUI, con `AppState` (`ObservableObject`) como estado central — un enum `Screen` en vez de `state.screen` |
| `@zxing/library` (JS) para leer código de barras | `AVFoundation` (`AVCaptureMetadataOutput`) nativo — sin dependencias externas |
| `<input type=file>` / `getUserMedia` para fotos | `UIImagePickerController` (cámara) |
| Credenciales en `localStorage` | La contraseña de aplicación de WordPress se guarda en el **Keychain** de iOS (no en `UserDefaults`), por ser una credencial real |
| Ajustes (categoría por defecto, token de Discogs, toggles de campos) en `localStorage` | `UserDefaults`, igual de no-sensible que en la web |
| "Conectar con mi tienda" redirige con query params en la misma página | Mismo flujo de WordPress (`authorize-application.php`), pero el retorno usa un **URL scheme propio** (`disqueriaapp://authorize`) que la app registra e intercepta con `.onOpenURL` |

Toda la lógica de negocio (armado del body del producto, adivinar categoría de
formato/condición/género a partir de Discogs, construir la descripción corta,
etc.) se portó 1:1 desde `Component` en `app/index.html` a `AppState.swift`.

## Estructura del proyecto

```
disqueria-ios-app/
  README.md                  este archivo
  original-plugin-reference/ copia del plugin de WordPress original (sin modificar), como referencia
  DisqueriaApp/
    project.yml               spec de XcodeGen (genera el .xcodeproj)
    DisqueriaApp/
      App/
        DisqueriaAppApp.swift  punto de entrada (@main)
        AppState.swift         estado + lógica de negocio (equivalente a Component en index.html)
      Models/
        Models.swift           tipos Codable: Discogs, categorías, productos WooCommerce
      Networking/
        APIClient.swift        helper REST genérico (URLSession + async/await)
        WooCommerceAPI.swift   cliente de los endpoints disqueria/v1 (auth por WP Application Password)
        DiscogsAPI.swift       búsqueda por código de barras + detalle de release
      Persistence/
        KeychainStore.swift    credenciales del sitio (Keychain)
        SettingsStore.swift    ajustes por sitio (UserDefaults)
      Views/
        RootView.swift         barra superior + switch entre pantallas
        LoginView.swift, SettingsView.swift, ScanView.swift, MatchResultsView.swift,
        NewUsedView.swift, PhotosView.swift, ConditionView.swift, DetailsView.swift,
        PriceView.swift, DoneView.swift, ProductListView.swift, EditProductView.swift
        BarcodeScannerView.swift  cámara + lectura de EAN/UPC (AVFoundation)
        CameraCaptureView.swift  cámara para fotografiar el disco
        Components.swift        estilos y controles reutilizables (botones, chips, etc.)
```

## Cómo compilarla (necesitas un Mac con Xcode)

Este proyecto se generó en un entorno Linux sin Xcode, así que el `.xcodeproj`
**no está incluido** — se genera con [XcodeGen](https://github.com/yonaskolb/XcodeGen)
a partir de `project.yml`, en vez de versionar el `.pbxproj` binario/frágil a mano.

1. Instala XcodeGen (una sola vez):
   ```
   brew install xcodegen
   ```
2. Genera el proyecto:
   ```
   cd disqueria-ios-app/DisqueriaApp
   xcodegen generate
   ```
   Esto crea `DisqueriaApp.xcodeproj` y `Generated/Info.plist`.
3. Abre `DisqueriaApp.xcodeproj` en Xcode.
4. En el target `DisqueriaApp` → *Signing & Capabilities*, selecciona tu Team
   (cuenta de desarrollador Apple) para poder correrla en un dispositivo físico.
5. Corre en un **iPhone físico** para probar cámara y escáner de código de
   barras (el simulador no tiene cámara). Requiere iOS 16+.

## Primer uso

1. Abre la app → pantalla de login.
2. Opción manual (recomendada): ingresa la URL de la tienda, tu usuario de
   WordPress y una **contraseña de aplicación** (Usuarios → Perfil →
   Contraseñas de aplicación en tu WordPress; requiere HTTPS).
3. Opción automática ("Conectar con mi tienda"): abre Safari hacia tu
   WordPress para autorizar la app y vuelve sola a la app vía el URL scheme
   `disqueriaapp://`.
4. Escanea un código de barras (o ingrésalo a mano), elige el resultado de
   Discogs, indica si el disco es nuevo o usado, fotografíalo si quieres,
   califica su estado (escala Goldmine), revisa los datos/categorías, pon
   precio y stock, y publica — queda como producto en tu WooCommerce.

## Notas

- El token de Discogs "genérico" que trae el plugin por defecto (mismo que en
  `disqueria-app-plugin.php`/`index.html`) sigue funcionando sin configurar
  nada; en Ajustes se puede reemplazar por un token personal de Discogs.
- Las imágenes de portada de Discogs se proxyan igual que en la web (vía
  `images.weserv.nl`) porque Discogs bloquea el hotlinking directo.
- Si el WordPress de destino tiene problemas de CORS o de hosting con el
  header `Authorization`, esos workarounds ya viven en el plugin PHP
  (`disqueria-app-plugin.php`) y no requieren nada adicional del lado de la
  app nativa — a diferencia de la web, una app iOS no está sujeta a CORS del
  navegador.
