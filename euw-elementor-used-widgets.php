<?php
/**
 * Plugin Name: EUW Elementor used widgets
 * Description: Displays the widgets which are currently used by elementor
 * Plugin URI:  https://github.com/kskonovalov/euw-elementor-used-widgets
 * GitHub Plugin URI: https://github.com/kskonovalov/euw-elementor-used-widgets
 * Version: 0.1.1
 * Author: Konstantin Konovalov
 * Author URI: https://kskonovalov.me
 * Text Domain: euw-elementor-used-widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
define( 'EUW_URL', plugin_dir_url( __FILE__ ) );

function euw_title() {
  return 'euw-elementor-used-widgets';
}

// add link to the menu
add_action( 'admin_menu', 'euw_add_link_to_menu', 999 );
function euw_add_link_to_menu() {
  add_submenu_page(
    'elementor',
    'Elementor used widgets',
    'Used widgets',
    'manage_options',
    euw_title(),
    'euw_main_func',
    100
  );
}

// add settings link to the plugins list
function euw_plugin_settings_link( $links ) {
  $list_text     = __( 'Used widgets', euw_title() );
  $settings_link = "<a href='admin.php?page=" . euw_title() . "'>{$list_text}</a>";
  array_unshift( $links, $settings_link );

  return $links;
}

$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'euw_plugin_settings_link' );

// page with the result
function euw_main_func() {
  $pageTitle = __( "Used elementor widgets", euw_title() );
  ?>
    <div class="wrap">
        <h2><?php echo $pageTitle; ?></h2>
      <?php

      // Check if Elementor installed and activated
      if ( ! did_action( 'elementor/loaded' ) ) {
        add_action( 'admin_notices', 'euw_admin_notice_missing_main_plugin' );
        echo "</div>";

        return;
      }

      $otherCategoryName = "other";
      // Get Registered widgets
      $registeredWidgetsData = \Elementor\Plugin::instance()->widgets_manager->get_widget_types_config( [] );
      $registeredWidgets     = [];
      foreach ( $registeredWidgetsData as $widgetID => $widgetFields ) {
        if ( isset( $widgetFields["categories"] ) && is_array( $widgetFields["categories"] ) ) {
          foreach ( $widgetFields["categories"] as $category ) {
            $registeredWidgets[ $category ][] = [
              "id"    => $widgetID,
              "title" => $widgetFields["title"]
            ];
          }
        } else {
          $registeredWidgets[ $otherCategoryName ][] = $widgetID;
        }
      }

      $usedPostTypes    = get_post_types( [] );
      $postTypesToUnset = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'elementor_font',
        'elementor_icons'
      ];
      foreach ( $usedPostTypes as $id => $post_type ) {
        if ( in_array( $post_type, $postTypesToUnset, true ) ) {
          unset( $usedPostTypes[ $id ] );
        }
      }
      // get all posts to check
      $postsQuery = new WP_Query;
      $posts      = $postsQuery->query( [
        'nopaging'            => true,
        'posts_per_page'      => - 1,
        'category'            => 0,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'post_type'           => $usedPostTypes,
        'post_status'         => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private' ),
        'exclude_from_search' => false
      ] );

      $usedWidgets       = [];
      $usedWidgetsByPage = [];
      foreach ( $posts as $post ) {
        $postData      = [
          'id'    => $post->ID,
          'link'  => get_the_permalink( $post ),
          'edit'  => get_edit_post_link( $post ),
          'title' => $post->post_title,
          'type'  => $post->post_type
        ];
        $elementorData = get_post_meta( $postData["id"], '_elementor_data', true );
        if ( ! empty( $elementorData ) ) {
          $elementorJson = json_decode( $elementorData, true );
          array_walk_recursive( $elementorJson, static function ( $value, $key ) use ( &$usedWidgets, &$usedWidgetsByPage, $postData ) {
            if ( $key === 'widgetType' ) {
              $usedWidgets[]                                  = $value;
              $usedWidgetsByPage[ $value ][ $postData["id"] ] = $postData;
            }
          } );
        }
      }

      $usedColor    = "#b35082";
      $unusedColor  = "#71b350";
      $usedIcon     = "&check;";
      $unusedIcon   = "&cross;";
      $categoryText = __( "Category", euw_title() );
      $editText     = __( "Edit", euw_title() );
      echo '<table cellspacing="0" cellpadding="0" class="widefat fixed" style="width: 800px; max-width: 100%;">';
      foreach ( $registeredWidgets as $categoryName => $category ) {
        echo "<thead><tr><th class='manage-column' style='width: 200px;'><b>{$categoryText}:</b> {$categoryName}</th><th class='manage-column'>Page</th></tr></thead><tbody>";
        foreach ( $category as $widget ) {
          if ( empty( $widget["id"] ) ) {
            // TODO: check skipped?
            continue;
          }

          $title = "";
          if ( ! empty( $widget["title"] ) ) {
            $title = $widget["title"];
          } else if ( ! empty( $widget["id"] ) ) {
            $title = $widget["id"];
          }
          if ( in_array( $widget["id"], $usedWidgets, true ) ) {
            $posts       = [];
            $statusColor = $usedColor;
            $statusIcon  = $usedIcon;
            if ( isset( $usedWidgetsByPage[ $widget["id"] ] ) && is_array( $usedWidgetsByPage[ $widget["id"] ] ) ) {
              foreach ( $usedWidgetsByPage[ $widget["id"] ] as $usedInPages ) {
                $posts[] = "<a href='{$usedInPages["link"]}' title='{$usedInPages["title"]}' target='_blank'>{$usedInPages["title"]}</a>
<a href='{$usedInPages["edit"]}' title='{$editText}' target='_blank'>(&para;)</a>";
              }
            }
            $posts = implode( ", ", $posts );
          } else {
            $posts       = "";
            $statusColor = $unusedColor;
            $statusIcon  = $unusedIcon;
          }


          echo "<tr><td style='color: {$statusColor}; width: 30px;'>{$statusIcon} {$title}</td><td>{$posts}</td></tr>";
        }
      }
      echo '</tbody></table>';
      ?>
    </div>
  <?php
}

function euw_admin_notice_missing_main_plugin() {

  if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

  $message = sprintf(
  /* translators: 1: Plugin name 2: Elementor */
    esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'elementor-test-extension' ),
    '<strong>' . esc_html__( 'Elementor used widgets', 'elementor-test-extension' ) . '</strong>',
    '<strong>' . esc_html__( 'Elementor', euw_title() ) . '</strong>'
  );

  printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

}
