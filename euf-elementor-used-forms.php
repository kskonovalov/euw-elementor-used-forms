<?php
/**
 * Plugin Name: EUF Elementor used forms
 * Description: Simple way to check all Elementor forms (Elementor Pro Form widget) through the site
 * Plugin URI:  https://github.com/kskonovalov/euf-elementor-used-forms
 * GitHub Plugin URI: https://github.com/kskonovalov/euf-elementor-used-forms
 * Version: 0.1.0
 * Author: Konstantin Konovalov
 * Author URI: https://kskonovalov.me
 * Text Domain: euf-elementor-used-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
define( 'EUF_URL', plugin_dir_url( __FILE__ ) );

function euf_title() {
  return 'euf-elementor-used-forms';
}

// add link to the menu
add_action( 'admin_menu', 'euf_add_link_to_menu', 999 );
function euf_add_link_to_menu() {
  add_submenu_page(
    'elementor',
    'Elementor used forms',
    'Used forms',
    'manage_options',
    euf_title(),
    'euf_main_func',
    100
  );
}

// add settings link to the plugins list
function euf_plugin_settings_link( $links ) {
  $list_text     = __( 'Used forms', euf_title() );
  $settings_link = "<a href='admin.php?page=" . euf_title() . "'>{$list_text}</a>";
  array_unshift( $links, $settings_link );

  return $links;
}

$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'euf_plugin_settings_link' );

// page with the result
function euf_main_func() {
  $pageTitle = __( "Used elementor forms", euf_title() );
  ?>
    <div class="wrap">
        <h2><?php echo $pageTitle; ?></h2>
      <?php

      // Check if Elementor installed and activated
      if ( ! did_action( 'elementor/loaded' ) ) {
        add_action( 'admin_notices', 'euf_admin_notice_missing_main_plugin' );
        echo "</div>";

        return;
      }

      $formWidgetID = "form";

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
        'post_status'         => array(
          'publish',
          'pending',
          'draft',
          'auto-draft',
          'future',
          'private'
        ),
        'exclude_from_search' => false
      ] );

      $formsList = [];
      foreach ( $posts as $post ) {
        $elementorData = get_post_meta( $post->ID, '_elementor_data', true );
        if ( ! empty( $elementorData ) ) {
          $elementorJson = json_decode( $elementorData, true );
          foreach ( $elementorJson as $key => $value ) {
            euf_look_for_forms( $key, $value, $formsList, $post->ID );
          }
        }
      }

      $editText        = __( "Edit in elementor", euf_title() );
      $titleForm       = __( "Form", euf_title() );
      $titleEmail      = __( "E-mail to", euf_title() );
      $titleRecaptcha  = __( "Recaptcha", euf_title() );
      $titleRecaptcha3 = __( "Recaptcha v3", euf_title() );
      $titleHoneypot   = __( "Honeypot", euf_title() );
      $titleSavesToDb   = __( "Collect submissions", euf_title() );
      $titleEmail2     = __( "E-mail to 2", euf_title() );
      $titleActions    = __( "Actions", euf_title() );
      $check           = "<span style='color:green; font-size: 30px;'>&check;</span>";
      $times           = "<span style='color:#530101; font-size: 26px;'>&times;</span>";
      echo '<table cellspacing="0" cellpadding="0" class="widefat fixed" style="width: 800px; max-width: 100%;">';
      echo "<thead><tr>";
      echo "<th class='manage-column'><b>{$titleForm}</b></th>";
      echo "<th class='manage-column'><b>{$titleEmail}</b></th>";
      echo "<th class='manage-column'><b>{$titleEmail2}</b></th>";
      echo "<th class='manage-column'><b>{$titleRecaptcha}</b></th>";
      echo "<th class='manage-column'><b>{$titleRecaptcha3}</b></th>";
      echo "<th class='manage-column'><b>{$titleHoneypot}</b></th>";
      echo "<th class='manage-column'><b>{$titleSavesToDb}</b></th>";
      echo "<th class='manage-column'><b>{$titleActions}</b></th>";
      echo "</tr></thead><tbody>";
      foreach ( $posts as $post ) {
        if ( isset( $formsList[ $post->ID ] ) ) {
          $link     = get_the_permalink( $post );
          $editLink = get_edit_post_link( $post );
          $editLink = str_replace( "action=edit", "action=elementor", $editLink );
          echo <<<ROW
<tr><td colspan="7"><h3><a href="{$link}" target="_blank">{$post->post_title}</a></h3> (<a href='{$editLink}' title='{$editText}' target='_blank'>{$editText}</a>)</td></tr>
ROW;
          foreach ( $formsList[ $post->ID ] as $form ) {
            $formName = $form["settings"]["form_name"];
            $emailTo  = $form["settings"]["email_to"];
            $emailTo2 = "";
            if ( isset( $form["settings"]["submit_actions"] ) && in_array( "email2", $form["settings"]["submit_actions"] ) ) {
              $emailTo2 = $form["settings"]["email_to_2"];
            }
            $formFields = [];
            foreach ( $form["settings"]["form_fields"] as $field ) {
              if ( isset( $field["field_label"] ) && ! empty( $field["field_label"] ) ) {
                $formFields[] = $field["field_label"];
              } else {
                if ( isset( $field["placeholder"] ) && ! empty( $field["placeholder"] ) ) {
                  $formFields[] = $field["placeholder"];
                } else {
                  if ( isset( $field["field_type"] ) && ! empty( $field["field_type"] ) ) {
                    $formFields[] = $field["field_type"];
                  }
                }
              }
            }
            $recaptcha  = in_array( "recaptcha", $formFields ) ? $check : $times;
            $recaptcha3 = in_array( "recaptcha_v3", $formFields ) ? $check : $times;
            $honeypot   = in_array( "honeypot", $formFields ) ? $check : $times;

            /* by default form action is email and collect form submissions */
            $formActions = "email";
            $collectSubmissions = $check;
            if ( isset( $form["settings"]["submit_actions"] ) && is_array( $form["settings"]["submit_actions"] ) ) {
              $formActions = implode( ", ", $form["settings"]["submit_actions"] );
              $collectSubmissions = in_array( "save-to-database", $form["settings"]["submit_actions"] ) ? $check : $times;
            }

            echo "<tr>";
            echo "<td class='manage-column'><h4>{$formName}</h4></td>";
            echo "<td class='manage-column' style='white-space: nowrap;'>{$emailTo}</td>";
            echo "<td class='manage-column' style='white-space: nowrap;'>{$emailTo2}</td>";
            echo "<td class='manage-column'>{$recaptcha}</td>";
            echo "<td class='manage-column'>{$recaptcha3}</td>";
            echo "<td class='manage-column'>{$honeypot}</td>";
            echo "<td class='manage-column'>{$collectSubmissions}</td>";
            echo "<td class='manage-column'>{$formActions}</td>";
            echo "</tr>";
          }
        }
      }
      echo '</tbody></table>';
      ?>
    </div>
  <?php
}

function euf_look_for_forms( $key, $value, &$formsList, $postID ) {
  if ( is_array( $value ) ) {
    if ( isset( $value["widgetType"] ) && $value["widgetType"] === "form" ) {
      $formsList[ $postID ][] = $value;
    } else {
      foreach ( $value as $keyIN => $valueIN ) {
        euf_look_for_forms( $keyIN, $valueIN, $formsList, $postID );
      }
    }
  }
}

function euf_admin_notice_missing_main_plugin() {

  if ( isset( $_GET['activate'] ) ) {
    unset( $_GET['activate'] );
  }

  $message = sprintf(
  /* translators: 1: Plugin name 2: Elementor */
    esc_html__( '"%1$s" requires "%2$s" to be installed and activated.',
      'elementor-test-extension' ),
    '<strong>' . esc_html__( 'Elementor used forms', 'elementor-test-extension' ) . '</strong>',
    '<strong>' . esc_html__( 'Elementor', euf_title() ) . '</strong>'
  );

  printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

}
