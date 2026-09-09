<?php
/**
 * Plugin Name: Disquería App Endpoints
 * Description: Endpoints REST para la app Disquería App: crear, listar, editar y eliminar productos de WooCommerce, y resolver etiquetas. Es de un solo local (no marketplace): cualquier usuario con permiso de editar productos en este WordPress puede usarlos. No expone claves de WooCommerce; usa las funciones nativas de WP/Woo. La autenticación es la de "Contraseñas de aplicación" propia de WordPress (activa por defecto en sitios con HTTPS) — no requiere instalar ningún otro plugin de login. Requiere WooCommerce activo.
 * Version: 2.0
 * Author: Disquería App
 */

if (!defined('ABSPATH')) exit;

// Fuerza las contraseñas de aplicación por si un plugin de seguridad o el hosting las desactivó
// (prioridad muy alta para que corra después de cualquier otro filtro que las bloquee).
add_filter('wp_is_application_passwords_available', '__return_true', PHP_INT_MAX);
add_filter('wp_is_application_passwords_available_for_user', '__return_true', PHP_INT_MAX);
add_filter('rest_allowed_cors_headers', function ($headers) {
  $headers[] = 'X-Disqueria-Auth';
  return $headers;
});

// Algunos hostings (PHP-FPM/nginx, Apache con suexec, etc.) no le pasan la cabecera Authorization
// a PHP como PHP_AUTH_USER/PHP_AUTH_PW, aunque la cabecera sí llega al servidor. Sin esto, las
// contraseñas de aplicación de WordPress no se autentican nunca en esos hostings.
if (!isset($_SERVER['PHP_AUTH_USER'])) {
  $da_auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
  if (!$da_auth_header && function_exists('getallheaders')) {
    foreach (getallheaders() as $da_h => $da_v) {
      if (strtolower($da_h) === 'authorization') { $da_auth_header = $da_v; break; }
    }
  }
  if ($da_auth_header && stripos($da_auth_header, 'basic ') === 0) {
    $da_decoded = base64_decode(trim(substr($da_auth_header, 6)));
    if ($da_decoded && strpos($da_decoded, ':') !== false) {
      list($da_user, $da_pass) = explode(':', $da_decoded, 2);
      $_SERVER['PHP_AUTH_USER'] = $da_user;
      $_SERVER['PHP_AUTH_PW'] = $da_pass;
    }
  }
}

// Autenticación propia por cabecera personalizada (no "Authorization"), para hostings
// que interceptan/eliminan esa cabecera estándar antes de que llegue a PHP.
$GLOBALS['da_auth_debug'] = 'sin intento de autenticación';
add_filter('determine_current_user', function ($user_id) {
  if ($user_id) { $GLOBALS['da_auth_debug'] = 'ya autenticado'; return $user_id; }
  $raw = $_SERVER['HTTP_X_DISQUERIA_AUTH'] ?? null;
  if (!$raw && function_exists('getallheaders')) {
    foreach (getallheaders() as $da_h => $da_v) {
      if (strtolower($da_h) === 'x-disqueria-auth') { $raw = $da_v; break; }
    }
  }
  if (!$raw) { $GLOBALS['da_auth_debug'] = 'la cabecera X-Disqueria-Auth no llegó a WordPress'; return $user_id; }
  $decoded = base64_decode(trim($raw));
  if (!$decoded || strpos($decoded, ':') === false) { $GLOBALS['da_auth_debug'] = 'la cabecera llegó pero no se pudo decodificar'; return $user_id; }
  list($username, $password) = explode(':', $decoded, 2);
  if (!function_exists('wp_authenticate_application_password')) { $GLOBALS['da_auth_debug'] = 'este WordPress no soporta contraseñas de aplicación (actualiza WP)'; return $user_id; }
  $user = wp_authenticate_application_password(null, $username, $password);
  if (is_wp_error($user)) { $GLOBALS['da_auth_debug'] = 'usuario "' . $username . '": ' . $user->get_error_message(); return $user_id; }
  if (!$user) { $GLOBALS['da_auth_debug'] = 'usuario "' . $username . '" no encontrado'; return $user_id; }
  $GLOBALS['da_auth_debug'] = 'autenticado como ' . $user->user_login;
  return $user->ID;
}, 10);

// La app queda lista para usar apenas se activa el plugin, en una URL corta: /app
function disqueria_app_url() { return home_url('/app/'); }

function disqueria_app_add_rewrites() {
  add_rewrite_rule('^app/?$', 'index.php?da_route=index', 'top');
  add_rewrite_rule('^app/support\.js$', 'index.php?da_route=support', 'top');
}
add_action('init', 'disqueria_app_add_rewrites');

add_filter('query_vars', function ($vars) { $vars[] = 'da_route'; return $vars; });

add_action('template_redirect', function () {
  $route = get_query_var('da_route');
  if (!$route) return;
  if ($route === 'index') {
    $rawPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (substr($rawPath, -1) !== '/') {
      $qs = $_SERVER['QUERY_STRING'] ? ('?' . $_SERVER['QUERY_STRING']) : '';
      wp_redirect(home_url('/app/') . $qs, 301);
      exit;
    }
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/app/index.html');
    exit;
  }
  if ($route === 'support') {
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(__DIR__ . '/app/support.js');
    exit;
  }
});

add_action('admin_menu', function () {
  add_menu_page('Disquería App', 'Disquería App', 'edit_products', 'disqueria-app', function () {
    $url = esc_url(disqueria_app_url());
    echo '<div class="wrap"><h1>Disquería App</h1>';
    echo '<p>Tu app para subir discos está lista. Ábrela en el celular o computador con el que vas a cargar inventario:</p>';
    echo '<p><a href="' . $url . '" target="_blank" class="button button-primary button-hero">Abrir Disquería App</a></p>';
    echo '<p><code>' . $url . '</code></p>';
    echo '<p>La primera vez, toca "Conectar con mi tienda" dentro de la app para autorizarla contra este WordPress.</p></div>';
  }, 'dashicons-album', 56);
});

add_action('admin_notices', function () {
  $screen = get_current_screen();
  if (!$screen || strpos($screen->id, 'plugins') === false) return;
  if (!get_transient('disqueria_app_just_activated')) return;
  delete_transient('disqueria_app_just_activated');
  $url = esc_url(disqueria_app_url());
  echo '<div class="notice notice-success is-dismissible"><p><strong>Disquería App</strong> está lista. <a href="' . $url . '" target="_blank">Ábrela aquí</a> y conéctala con este WordPress.</p></div>';
});

register_activation_hook(__FILE__, function () {
  set_transient('disqueria_app_just_activated', 1, 60);
  disqueria_app_add_rewrites();
  flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

add_action('rest_api_init', function () {

  $can_manage = function () {
    $uid = get_current_user_id();
    if (!$uid) {
      return new WP_Error('no_auth', 'No se detectó tu sesión (' . ($GLOBALS['da_auth_debug'] ?? 'sin info') . ').', ['status' => 401]);
    }
    if (current_user_can('edit_products') || current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
      return true;
    }
    $user = wp_get_current_user();
    return new WP_Error('no_cap', 'Tu usuario (' . $user->user_login . ', rol: ' . implode(', ', $user->roles) . ') no tiene permiso para gestionar productos.', ['status' => 403]);
  };

  register_rest_route('disqueria/v1', '/site-info', [
    'methods' => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function () {
      $logo = '';
      $logo_id = get_theme_mod('custom_logo');
      if ($logo_id) {
        $src = wp_get_attachment_image_src($logo_id, 'medium');
        if ($src) $logo = $src[0];
      }
      if (!$logo && function_exists('has_site_icon') && has_site_icon()) {
        $logo = get_site_icon_url(270);
      }
      return ['name' => get_bloginfo('name'), 'logo' => $logo];
    },
  ]);

  register_rest_route('disqueria/v1', '/media', [
    'methods' => 'POST',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      $filename = $req->get_header('content_disposition');
      if ($filename && preg_match('/filename="?([^"]+)"?/', $filename, $m)) $filename = sanitize_file_name($m[1]);
      else $filename = 'foto-' . time() . '.jpg';
      $bits = wp_upload_bits($filename, null, $req->get_body());
      if (!empty($bits['error'])) return new WP_Error('upload_error', $bits['error'], ['status' => 500]);
      $attachment = [
        'post_mime_type' => $req->get_header('content_type') ?: 'image/jpeg',
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit',
      ];
      $attach_id = wp_insert_attachment($attachment, $bits['file']);
      if (is_wp_error($attach_id) || !$attach_id) return new WP_Error('attach_error', 'No se pudo crear el adjunto', ['status' => 500]);
      require_once ABSPATH . 'wp-admin/includes/image.php';
      $meta = wp_generate_attachment_metadata($attach_id, $bits['file']);
      wp_update_attachment_metadata($attach_id, $meta);
      return ['id' => $attach_id, 'source_url' => $bits['url']];
    },
  ]);

  register_rest_route('disqueria/v1', '/resolve-tag', [
    'methods' => 'POST',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      $name = sanitize_text_field($req->get_param('name'));
      if (!$name) return new WP_Error('bad_request', 'Falta name', ['status' => 400]);

      $term = get_term_by('name', $name, 'product_tag');
      if ($term) return ['id' => $term->term_id];

      $result = wp_insert_term($name, 'product_tag');
      if (is_wp_error($result)) {
        if (!empty($result->error_data['term_exists'])) {
          return ['id' => (int) $result->error_data['term_exists']];
        }
        return new WP_Error('tag_error', $result->get_error_message(), ['status' => 500]);
      }
      return ['id' => (int) $result['term_id']];
    },
  ]);

  register_rest_route('disqueria/v1', '/categories', [
    'methods' => 'GET',
    'permission_callback' => $can_manage,
    'callback' => function () {
      $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
      if (is_wp_error($terms)) return new WP_Error('cat_error', $terms->get_error_message(), ['status' => 500]);
      return array_map(function ($t) { return ['id' => $t->term_id, 'name' => $t->name]; }, $terms);
    },
  ]);

  register_rest_route('disqueria/v1', '/categories', [
    'methods' => 'POST',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      $name = sanitize_text_field($req->get_param('name'));
      if (!$name) return new WP_Error('bad_request', 'Falta name', ['status' => 400]);
      $term = get_term_by('name', $name, 'product_cat');
      if ($term) return ['id' => $term->term_id, 'name' => $term->name];
      $result = wp_insert_term($name, 'product_cat');
      if (is_wp_error($result)) {
        if (!empty($result->error_data['term_exists'])) {
          $t = get_term($result->error_data['term_exists'], 'product_cat');
          return ['id' => (int) $t->term_id, 'name' => $t->name];
        }
        return new WP_Error('cat_error', $result->get_error_message(), ['status' => 500]);
      }
      return ['id' => (int) $result['term_id'], 'name' => $name];
    },
  ]);

  register_rest_route('disqueria/v1', '/products', [
    'methods' => 'GET',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      if (!class_exists('WC_Product_Simple')) return new WP_Error('no_woo', 'WooCommerce no está activo', ['status' => 500]);
      $ids = wc_get_products(['limit' => 100, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids']);
      $out = [];
      foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p) continue;
        $img_id = $p->get_image_id();
        $out[] = [
          'id' => $p->get_id(),
          'name' => $p->get_name(),
          'regular_price' => $p->get_regular_price(),
          'stock_quantity' => $p->get_stock_quantity(),
          'featured_image' => $img_id ? wp_get_attachment_url($img_id) : '',
        ];
      }
      return $out;
    },
  ]);

  register_rest_route('disqueria/v1', '/products', [
    'methods' => 'POST',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      if (!class_exists('WC_Product_Simple')) return new WP_Error('no_woo', 'WooCommerce no está activo', ['status' => 500]);
      $b = $req->get_json_params();
      $product = new WC_Product_Simple();
      $product->set_name(sanitize_text_field($b['name'] ?? 'Producto'));
      $product->set_regular_price((string) ($b['regular_price'] ?? '0'));
      $product->set_manage_stock(true);
      $product->set_stock_quantity((int) ($b['stock_quantity'] ?? 0));
      $product->set_description(wp_kses_post($b['description'] ?? ''));
      $product->set_short_description(wp_kses_post($b['short_description'] ?? ''));
      if (!empty($b['categories'])) $product->set_category_ids(array_map('intval', $b['categories']));
      if (!empty($b['tags'])) $product->set_tag_ids(array_map(function ($t) { return (int) $t['id']; }, $b['tags']));
      $product->save();

      if (!empty($b['meta_data']) && is_array($b['meta_data'])) {
        foreach ($b['meta_data'] as $m) {
          if (!empty($m['key'])) $product->update_meta_data(sanitize_key($m['key']), sanitize_text_field($m['value'] ?? ''));
        }
      }
      if (!empty($b['featured_image']['src'])) {
        $img_id = attachment_url_to_postid($b['featured_image']['src']);
        if ($img_id) $product->set_image_id($img_id);
      }
      if (!empty($b['gallery_images']) && is_array($b['gallery_images'])) {
        $gallery_ids = [];
        foreach ($b['gallery_images'] as $g) {
          if (!empty($g['src'])) { $gid = attachment_url_to_postid($g['src']); if ($gid) $gallery_ids[] = $gid; }
        }
        if ($gallery_ids) $product->set_gallery_image_ids($gallery_ids);
      }
      $product->save();

      return [
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'permalink' => get_permalink($product->get_id()),
      ];
    },
  ]);

  register_rest_route('disqueria/v1', '/products/(?P<id>\d+)', [
    'methods' => 'PUT',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      $product = wc_get_product((int) $req->get_param('id'));
      if (!$product) return new WP_Error('not_found', 'Producto no encontrado', ['status' => 404]);
      $b = $req->get_json_params();
      if (isset($b['name'])) $product->set_name(sanitize_text_field($b['name']));
      if (isset($b['regular_price'])) $product->set_regular_price((string) $b['regular_price']);
      if (isset($b['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity((int) $b['stock_quantity']); }
      $product->save();
      return ['ok' => true, 'id' => $product->get_id()];
    },
  ]);

  register_rest_route('disqueria/v1', '/products/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'permission_callback' => $can_manage,
    'callback' => function (WP_REST_Request $req) {
      $product = wc_get_product((int) $req->get_param('id'));
      if (!$product) return new WP_Error('not_found', 'Producto no encontrado', ['status' => 404]);
      $product->delete(true);
      return ['ok' => true];
    },
  ]);
});
