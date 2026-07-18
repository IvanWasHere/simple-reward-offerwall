<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * MediaController (admin) — read-only view of the WordPress media library's images,
 * so the admin SPA (which runs on the front-end, without wp.media) can offer a
 * picker (e.g. for the app icon). Guarded by Guard::role('admin').
 */
class MediaController extends RestController
{
  public function index()
  {
    $search = trim((string) $this->request->get_param('s'));

    $items = get_posts([
      'post_type'      => 'attachment',
      'post_mime_type' => 'image',
      'post_status'    => 'inherit',
      'posts_per_page' => 60,
      'orderby'        => 'date',
      'order'          => 'DESC',
      's'              => $search,
    ]);

    $media = array_map(function ($att) {
      return [
        'id'    => (int) $att->ID,
        'title' => get_the_title($att->ID),
        'url'   => (string) (wp_get_attachment_image_url($att->ID, 'full') ?: wp_get_attachment_url($att->ID)),
        'thumb' => (string) (wp_get_attachment_image_url($att->ID, 'thumbnail') ?: wp_get_attachment_url($att->ID)),
        'mime'  => (string) $att->post_mime_type,
      ];
    }, $items ?: []);

    return $this->response(['media' => $media]);
  }
}
