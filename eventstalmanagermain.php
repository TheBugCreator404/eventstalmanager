<?php
/*
Plugin Name: EventStableManager
Description: Plugin voor het beheer van een paardenstal tijdens een evenement. Deze versie gebruikt CF7-formulieren voor aanmelden en afmelden.
Version: 3.0.3
Author: Stein Fraza
*/

// Voorkom directe toegang
if ( ! defined( 'ABSPATH' ) ) exit;

// -----------------------------------------------------
// INSTALLATIE & DATABASE
// -----------------------------------------------------

function esm_install() {
    global $wpdb;
    $esm_db_version = '1.2';
    
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      stalgang VARCHAR(10) NOT NULL,
      boxnummer INT NOT NULL,
      current_status VARCHAR(50) NOT NULL,
      previous_status VARCHAR(50) DEFAULT '',
      last_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
      modified_by VARCHAR(100) DEFAULT '',
      PRIMARY KEY  (id),
      UNIQUE KEY unique_box (stalgang, boxnummer)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    add_option( 'esm_db_version', $esm_db_version );
}
register_activation_hook( __FILE__, 'esm_install' );

function esm_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
       id mediumint(9) NOT NULL AUTO_INCREMENT,
       stalgang varchar(10) NOT NULL,
       boxnummer int NOT NULL,
       new_status varchar(50) NOT NULL,
       old_status varchar(50) NOT NULL,
       datetime datetime NOT NULL,
       modified_by varchar(50) NOT NULL,
       modification_type varchar(50) NOT NULL,
       PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'esm_create_log_table');

// -----------------------------------------------------
// ADMIN MENU'S & INSTELLINGEN
// -----------------------------------------------------

function esm_register_admin_menu() {
    add_menu_page(
        'EventStableManager',
        'EventStableManager',
        'manage_options',
        'esm_main',
        'esm_main_page',
        'dashicons-admin-site-alt3',
        26
    );

    add_submenu_page(
        'esm_main',
        'Bulk edit',
        'Bulk edit',
        'manage_options',
        'esm_bulk',
        'esm_bulk_update_page'
    );
    
    add_submenu_page(
        'esm_main',              // Parent slug (pas dit aan naar jouw hoofdplugin-menu slug)
        'Wijzigingslog',         // Paginatitel
        'Wijzigingslog',         // Menutitel
        'manage_options',        // Capability (alleen admin)
        'esm_logs',              // Menu slug
        'esm_render_logs_page'   // Callback-functie die de pagina weergeeft
    );

    add_submenu_page(
        'esm_main',
        'Instellingen',
        'Instellingen',
        'manage_options',
        'esm_settings',
        'esm_settings_page'
    );
    
    add_submenu_page(
        'esm_main',
        'Stallen & Boxen',
        'Stallen & Boxen',
        'manage_options',
        'esm_stallen',
        'esm_stallen_page'
    );

    add_submenu_page(
        'esm_main',                   // Parent slug van je plugin
        'QR Code Generator',          // Pagina titel
        'QR Code Generator',          // Menu titel
        'manage_options',             // Capability
        'esm_qrcode_generator',       // Menu slug
        'esm_qrcode_zip_page'         // Callback functie (die hierboven is gedefinieerd)
    ); 
    
    add_submenu_page(
        'esm_main',
        'Shortcodes',
        'Shortcodes',
        'manage_options',
        'esm_shortcodes',
        'esm_shortcodes_page'
    );
}
add_action( 'admin_menu', 'esm_register_admin_menu' );

// ----- MAIN ADMIN PAGE -----
function esm_main_page() {
    echo '<div class="wrap"><h1>EventStableManager</h1>';
    echo '<p>Welkom bij de EventStableManager plugin.</p>';
    echo do_shortcode("[eventstable_dashboard]");
    echo '<p>Gebruik de submenu-items om instellingen, stallen & boxen configuratie en shortcodes te beheren.</p>';
    echo '</div>';
}

//----- BULK EDIT PAGINA -----
function esm_bulk_update_page() {
    if ( isset($_POST['esm_bulk_submit']) && check_admin_referer('esm_bulk_save','esm_bulk_nonce') ) {
         $stalgang = sanitize_text_field($_POST['stalgang']);
         $box_range = sanitize_text_field($_POST['box_range']);
         $new_status = sanitize_text_field($_POST['new_status']);
         global $wpdb;
         $table_name = $wpdb->prefix . 'eventstable_manager';
         list($start, $end) = explode('-', $box_range);
         $start = intval($start);
         $end = intval($end);
         for ($i = $start; $i <= $end; $i++) {
             $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $i));
             if($existing) {
                  // Definieer oude status
                  $old_status = $existing->current_status;
                  $result = $wpdb->update($table_name, array(
                      'previous_status' => $old_status,
                      'current_status' => $new_status,
                      'last_modified' => current_time('mysql'),
                      'modified_by'   => 'admin'
                  ), array(
                      'stalgang' => $stalgang,
                      'boxnummer' => $i
                  ));
                  if($result !== false) {
                      // Log de wijziging met type 'bulk edit'
                      esm_log_modification($stalgang, $i, $new_status, $old_status, 'admin', 'bulk edit');
                  }
             } else {
                  $old_status = 'n.v.t.'; // Aangezien er geen bestaand record is
                  $result = $wpdb->insert($table_name, array(
                      'stalgang' => $stalgang,
                      'boxnummer' => $i,
                      'current_status' => $new_status,
                      'previous_status' => 'n.v.t.',
                      'last_modified' => current_time('mysql'),
                      'modified_by'   => 'admin'
                  ));
                  if($result !== false) {
                      esm_log_modification($stalgang, $i, $new_status, $old_status, 'admin', 'bulk edit');
                  }
             }
         }
         echo '<div class="updated"><p>Bulk update uitgevoerd voor Stalgang '. esc_html($stalgang) .' van box '. esc_html($box_range) .' naar status '. esc_html($new_status) .'.</p></div>';
    }
    // HTML-formulier weergeven...
    echo '<div class="wrap">';
    echo '<h1>Bulk Status Wijzigen</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('esm_bulk_save','esm_bulk_nonce');
    
    // Haal de stallen uit de opties (zorg dat je deze configuratie al opslaat in 'esm_stallen')
    $stallen = get_option('esm_stallen', array());
    echo '<p><label>Stalgang: <select name="stalgang" required>';
    if ( ! empty( $stallen ) ) {
        foreach ( $stallen as $row ) {
            if ( isset( $row['stalgang'] ) ) {
                echo '<option value="' . esc_attr( $row['stalgang'] ) . '">' . esc_html( $row['stalgang'] ) . '</option>';
            }
        }
    } else {
        echo '<option value="">Geen stallen gevonden</option>';
    }
    echo '</select></label></p>';

    echo '<p><label>Box Range (bijv. 1-12): <input type="text" name="box_range" required /></label></p>';
    echo '<p><label>Nieuwe Status: <select name="new_status">';
    $statuses = array('n.v.t.', 'Vrij', 'Verhuurd', 'Aangemeld', 'Afgemeld', 'Gebruikt', 'Niet beschikbaar', 'Noodstal');
    foreach ($statuses as $status) {
         echo '<option value="' . esc_attr($status) . '">' . esc_html($status) . '</option>';
    }
    echo '</select></label></p>';
    echo '<p><input type="submit" name="esm_bulk_submit" class="button button-primary" value="Update in Bulk" /></p>';
    echo '</form>';
    echo '</div>';
}



// ----- INSTELLINGEN PAGINA -----
// Hier stel je onder andere de statuskleuren, toegestane statussen voor aanmelden/afmelden,
// de redirect pagina én de CF7 formulier ID’s voor de acties in.
function esm_settings_page() {
    if ( isset($_POST['esm_settings_submit']) && check_admin_referer('esm_settings_save','esm_settings_nonce') ) {
        // Statuskleuren
        $default_statuses = array('n.v.t.', 'Vrij', 'Verhuurd', 'Aangemeld', 'Afgemeld', 'Gebruikt', 'Niet beschikbaar', 'Noodstal');
        $status_colors = array();
        foreach ( $default_statuses as $status ) {
            $color_key = 'esm_color_' . sanitize_title($status);
            if(isset($_POST[$color_key])){
                $status_colors[$status] = sanitize_text_field($_POST[$color_key]);
            }
        }
        update_option('esm_status_colors', $status_colors);
        
        // Toegestane statussen voor aanmelden
        $allowed_aanmelden = isset($_POST['esm_allowed_aanmelden']) ? array_map('sanitize_text_field', $_POST['esm_allowed_aanmelden']) : array();
        update_option('esm_allowed_aanmelden', $allowed_aanmelden);
        
        // Toegestane statussen voor afmelden
        $allowed_afmelden = isset($_POST['esm_allowed_afmelden']) ? array_map('sanitize_text_field', $_POST['esm_allowed_afmelden']) : array();
        update_option('esm_allowed_afmelden', $allowed_afmelden);
        
        // Redirect pagina na aan-/afmelding
        $redirect_page = isset($_POST['esm_redirect_page']) ? sanitize_text_field($_POST['esm_redirect_page']) : '';
        update_option('esm_redirect_page', $redirect_page);
        
        $esm_left_column_order = isset($_POST['esm_left_column_order']) ? sanitize_text_field($_POST['esm_left_column_order']) : 'normal';
        update_option('esm_left_column_order', $esm_left_column_order);

        // CF7 Formulier ID's voor de acties
        $cf7_aanmelden = isset($_POST['esm_cf7_aanmelden_form_id']) ? sanitize_text_field($_POST['esm_cf7_aanmelden_form_id']) : '';
        update_option('esm_cf7_aanmelden_form_id', $cf7_aanmelden);
        $cf7_afmelden = isset($_POST['esm_cf7_afmelden_form_id']) ? sanitize_text_field($_POST['esm_cf7_afmelden_form_id']) : '';
        update_option('esm_cf7_afmelden_form_id', $cf7_afmelden);
        $cf7_update = isset($_POST['esm_cf7_update_form_id']) ? sanitize_text_field($_POST['esm_cf7_update_form_id']) : '';
        update_option('esm_cf7_update_form_id', $cf7_update);

        $update_password = isset($_POST['esm_update_password']) ? sanitize_text_field($_POST['esm_update_password']) : '';
        update_option('esm_update_password', $update_password);

        if ( isset($_POST['esm_dashboard_refresh_interval']) ) {
            // Sanitize de input, bijvoorbeeld als een integer
            $refresh_interval = intval($_POST['esm_dashboard_refresh_interval']);
            // Sla de instelling op, bijvoorbeeld standaard 30000 ms als er geen waarde is
            update_option('esm_dashboard_refresh_interval', $refresh_interval);
        }
        
        $qr_redirect_page = isset($_POST['esm_qr_redirect_page']) ? sanitize_text_field($_POST['esm_qr_redirect_page']) : '';
        update_option('esm_qr_redirect_page', $qr_redirect_page);


        echo '<div class="updated"><p>Instellingen opgeslagen.</p></div>';
    }
    
    $default_statuses = array('n.v.t.', 'Vrij', 'Verhuurd', 'Aangemeld', 'Afgemeld', 'Gebruikt', 'Niet beschikbaar', 'Noodstal');
    $status_colors = get_option('esm_status_colors', array());
    $allowed_aanmelden = get_option('esm_allowed_aanmelden', array());
    $allowed_afmelden = get_option('esm_allowed_afmelden', array());
    $redirect_page = get_option('esm_redirect_page', '');
    $cf7_aanmelden = get_option('esm_cf7_aanmelden_form_id', '');
    $cf7_afmelden = get_option('esm_cf7_afmelden_form_id', '');
    ?>
    <div class="wrap">
        <h1>Instellingen - EventStableManager</h1>
        <form method="post" action="">
            <?php wp_nonce_field('esm_settings_save', 'esm_settings_nonce'); ?>
            <h2>Status Kleuren</h2>
            <table class="form-table">
                <tbody>
                <?php foreach($default_statuses as $status): 
                    $color = isset($status_colors[$status]) ? $status_colors[$status] : '#ffffff'; ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($status); ?></th>
                        <td>
                            <input type="text" name="esm_color_<?php echo sanitize_title($status); ?>" value="<?php echo esc_attr($color); ?>" class="regular-text" />
                            <p class="description">Stel de kleur in voor de status "<?php echo esc_html($status); ?>".</p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Aanmelden toestaan voor statussen</h2>
            <table class="form-table">
                <tbody>
                <?php foreach($default_statuses as $status): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($status); ?></th>
                        <td>
                            <input type="checkbox" name="esm_allowed_aanmelden[]" value="<?php echo esc_attr($status); ?>" <?php checked(in_array($status, $allowed_aanmelden)); ?> />
                            <label>Sta aanmelden toe voor status "<?php echo esc_html($status); ?>"</label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Afmelden toestaan voor statussen</h2>
            <table class="form-table">
                <tbody>
                <?php foreach($default_statuses as $status): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($status); ?></th>
                        <td>
                            <input type="checkbox" name="esm_allowed_afmelden[]" value="<?php echo esc_attr($status); ?>" <?php checked(in_array($status, $allowed_afmelden)); ?> />
                            <label>Sta afmelden toe voor status "<?php echo esc_html($status); ?>"</label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Redirect Pagina na Aan-/Afmelding</h2>
            <p>Selecteer de pagina waarnaar gebruikers doorgestuurd worden na een succesvolle aan- of afmelding.</p>
            <?php
            $args = array(
                'name'              => 'esm_redirect_page',
                'selected'          => $redirect_page,
                'show_option_none'  => '-- Selecteer pagina --',
                'option_none_value' => '',
            );
            wp_dropdown_pages($args);
            ?>
            
            <h2>Dashboard Instellingen</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Linkerkolom volgorde</th>
                    <td>
                        <select name="esm_left_column_order">
                            <option value="normal" <?php selected(get_option('esm_left_column_order', 'normal'), 'normal'); ?>>Normaal</option>
                            <option value="reversed" <?php selected(get_option('esm_left_column_order', 'normal'), 'reversed'); ?>>Omgekeerd</option>
                        </select>
                        <p class="description">Kies of de linkerkolom van de dashboardtabel normaal of omgekeerd moet worden weergegeven.</p>
                    </td>
                </tr>
            </table>

            <h2>CF7 Formulier Instellingen</h2>
            <p>Voer hieronder de Form ID’s in (zoals te vinden in de CF7 beheerpagina) voor de formulieren:</p>
            <p>
                <label>CF7 Form ID voor Aanmelden: 
                    <input type="text" name="esm_cf7_aanmelden_form_id" value="<?php echo esc_attr($cf7_aanmelden); ?>" />
                </label>
            </p>
            <p>
                <label>CF7 Form ID voor Afmelden: 
                    <input type="text" name="esm_cf7_afmelden_form_id" value="<?php echo esc_attr($cf7_afmelden); ?>" />
                </label>
            </p>
            <p>
                <label>CF7 Form ID voor Individuele Statusupdate: 
                    <input type="text" name="esm_cf7_update_form_id" value="<?php echo esc_attr(get_option('esm_cf7_update_form_id', '')); ?>" />
                </label>
            </p>
            <p> </P>
            <p>
                <label>Algemeen Wachtwoord voor Statusupdate: 
                    <input type="password" name="esm_update_password" value="<?php echo esc_attr(get_option('esm_update_password', '')); ?>" />
                </label>
            </p>

            <p>
                <label>Dashboard refresh interval (in milliseconden):
                    <input type="number" name="esm_dashboard_refresh_interval" value="<?php echo esc_attr(get_option('esm_dashboard_refresh_interval', '30000')); ?>" />
                </label>
            </p>
            
            <h2>CSS Klassen en Tags</h2>
            <p>De volgende CSS klassen worden gebruikt in de front-end (voor eigen CSS-aanpassingen):</p>
            <ul>
                <li><code>.esm-dashboard</code>: Container voor het dashboard.</li>
                <li><code>.esm-stalgang-container</code>: Container per stalgang.</li>
                <li><code>.esm-stalgang-header</code>: Header met de naam van de stalgang.</li>
                <li><code>.esm-box</code>: Individuele box in het dashboard.</li>
                <li><code>.esm-popup</code>: Pop-up venster voor box details.</li>
                <li><code>.esm-popup-content</code>: Contentgebied in de pop-up.</li>
            </ul>

            <h2>QR Code Instellingen</h2>
            <p>Selecteer de pagina waar de QR-codes naar moeten verwijzen.</p>
            <?php
            $args = array(
                'name'              => 'esm_qr_redirect_page',
                'selected'          => get_option('esm_qr_redirect_page', ''),
                'show_option_none'  => '-- Selecteer pagina --',
                'option_none_value' => '',
            );
            wp_dropdown_pages($args);
            ?>
            
            <?php submit_button('Instellingen Opslaan', 'primary', 'esm_settings_submit'); ?>
        </form>
    </div>
    <?php
}

// ----- STALLEN & BOXEN CONFIGURATIE PAGINA -----
function esm_stallen_page() {
    if ( isset($_POST['esm_stallen_submit']) && check_admin_referer('esm_stallen_save','esm_stallen_nonce') ) {
        $stallen = array();
        if ( isset($_POST['stalgang']) && is_array($_POST['stalgang']) ) {
            foreach($_POST['stalgang'] as $index => $stalgang) {
                $stalgang = sanitize_text_field($stalgang);
                $box_range = sanitize_text_field($_POST['box_range'][$index]);
                $kolom = intval($_POST['kolom'][$index]);
                if (!empty($stalgang) && !empty($box_range) && $kolom > 0) {
                    $stallen[] = array(
                        'stalgang'  => $stalgang,
                        'box_range' => $box_range,
                        'kolom'     => $kolom
                    );
                }
            }
        }
        update_option('esm_stallen', $stallen);
        echo '<div class="updated"><p>Stallen & Boxen configuratie opgeslagen.</p></div>';
    }
    
    $stallen = get_option('esm_stallen', array());
    ?>
    <div class="wrap">
        <h1>Stallen & Boxen Configuratie</h1>
        <p>Voeg per rij de volgende gegevens in: [Stalgang=LETTER, Box=bereik, Kolom=aantal kolommen].<br>
           Voorbeeld: [Stalgang=A, Box=1-12, Kolom=2] of [Stalgang=B, Box=1-6, Kolom=1].
        </p>
        <form method="post" action="">
            <?php wp_nonce_field('esm_stallen_save', 'esm_stallen_nonce'); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Stalgang (letter)</th>
                        <th>Box Range (bijv. 1-12)</th>
                        <th>Kolom (aantal kolommen)</th>
                    </tr>
                </thead>
                <tbody id="esm_stallen_body">
                <?php 
                if(!empty($stallen)){
                    foreach($stallen as $row){
                        ?>
                        <tr>
                            <td><input type="text" name="stalgang[]" value="<?php echo esc_attr($row['stalgang']); ?>" /></td>
                            <td><input type="text" name="box_range[]" value="<?php echo esc_attr($row['box_range']); ?>" /></td>
                            <td><input type="number" name="kolom[]" value="<?php echo esc_attr($row['kolom']); ?>" min="1" /></td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td><input type="text" name="stalgang[]" /></td>
                        <td><input type="text" name="box_range[]" placeholder="bijv. 1-12" /></td>
                        <td><input type="number" name="kolom[]" value="1" min="1" /></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <p><a href="#" id="esm_add_row">Voeg een rij toe</a></p>
            <?php submit_button('Configuratie Opslaan', 'primary', 'esm_stallen_submit'); ?>
        </form>
    </div>
    <script>
    document.getElementById('esm_add_row').addEventListener('click', function(e) {
        e.preventDefault();
        var tbody = document.getElementById('esm_stallen_body');
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="stalgang[]" /></td>' +
                       '<td><input type="text" name="box_range[]" placeholder="bijv. 1-12" /></td>' +
                       '<td><input type="number" name="kolom[]" value="1" min="1" /></td>';
        tbody.appendChild(tr);
    });
    </script>
    <?php
}

function esm_qrcode_zip_page() {
    echo '<div class="wrap">';
    echo '<h1>QR Code Generator</h1>';
    echo '<p>Klik op de knop hieronder om de QR-codes als ZIP-bestand te downloaden.</p>';
    
    // Maak de download URL via admin-ajax.php met de actie parameter
    $download_url = admin_url('admin-ajax.php?action=esm_generate_qrcode_zip');
    echo '<a href="' . esc_url($download_url) . '" class="button button-primary">Download QR Codes</a>';
    echo '</div>';
}

// ----- SHORTCODES PAGINA -----
function esm_shortcodes_page() {
    ?>
    <div class="wrap">
        <h1>Shortcodes voor EventStableManager</h1>
        <p>Kopieer en plak de volgende shortcodes in je front-end pagina's:</p>
        <h2>Dashboard</h2>
        <code>[eventstable_dashboard]</code>
        <h2>Huurders</h2>
        <code>[eventstable_huurders]</code>
        <p>Voor de Huurders shortcode wordt het CF7 formulier getoond (afhankelijk van de huidige status) en zoekt het naar de GET-parameters: <code>?stal=A&amp;box=3</code></p>
    </div>
    <?php
}

// -----------------------------------------------------
// SHORTCODE FUNCTIES
// -----------------------------------------------------

// ----- DASHBOARD SHORTCODE -----
// Toont een overzicht met de boxen per stalgang in een grid.
function esm_dashboard_shortcode() {
    ob_start();
    ?>
    <div id="esm-dashboard-content">
         <?php echo esm_render_dashboard(); // Toon initiale data bij paginalaad ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('eventstable_dashboard', 'esm_dashboard_shortcode');

// ----- HUURDERS SHORTCODE -----
// Toont de boxdetails op basis van de GET-parameters en geeft, afhankelijk van de huidige status,
// het bijbehorende CF7 formulier weer (aanmelden of afmelden).
function esm_huurders_shortcode() {
    ob_start();
    ?>
    <div id="esm-huurders-content">
         <!-- Container voor de boxgegevens -->
         <div id="box-details">
             <p>Bezig met laden…</p>
         </div>
         <!-- CF7 formulieren; standaard verborgen -->
         <div id="cf7-aanmelden" style="display: none;">
         <h2>Aanmelden</h2>
             <?php echo do_shortcode('[contact-form-7 id="' . intval(get_option('esm_cf7_aanmelden_form_id')) . '"]'); ?>
         </div>
         <div id="cf7-afmelden" style="display: none;">
            <h2>Afmelden</h2>
             <?php echo do_shortcode('[contact-form-7 id="' . intval(get_option('esm_cf7_afmelden_form_id')) . '"]'); ?>
         </div>
         <div id="message" style="display: none;">
            <h2>Stal huren?</h2>
            <p>Neem contact op met de stalmeester of het secetariaat</p>
         </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('eventstable_huurders', 'esm_huurders_shortcode');


// -----------------------------------------------------
// CF7 INTEGRATIE
// -----------------------------------------------------

// Hook in CF7 voordat de mail wordt verzonden om de database te updaten
function esm_cf7_before_send_mail_handler( $contact_form ) {
    // Haal de formulier-ID’s voor aanmelden en afmelden op
    $aanmelden_form_id = intval( get_option('esm_cf7_aanmelden_form_id') );
    $afmelden_form_id  = intval( get_option('esm_cf7_afmelden_form_id') );
    $form_id = intval( $contact_form->id() );
    
    // Verwerk alleen als het formulier één van de twee is
    if ( $form_id !== $aanmelden_form_id && $form_id !== $afmelden_form_id ) {
         return;
    }
    
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
         return;
    }
    
    $data = $submission->get_posted_data();
    
    // Zorg dat de benodigde velden aanwezig zijn
    if ( empty($data['stal']) || empty($data['box']) ) {
         return;
    }
    
    // Haal de extra velden op uit het formulier
    $your_name = isset($data['your-name']) ? sanitize_text_field($data['your-name']) : '';
    $horse     = isset($data['horse']) ? sanitize_text_field($data['horse']) : '';
    $phone     = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    
    // Combineer de waarden in één string
    $modified_by = trim($your_name);
    if( !empty($horse) ) {
        $modified_by .= ' (Paard: ' . $horse;
        if( !empty($phone) ) {
            $modified_by .= ', Tel: ' . $phone;
        }
        $modified_by .= ')';
    }

    $stalgang  = sanitize_text_field($data['stal']);
    $boxnummer = intval($data['box']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $box = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer) );
    
    // Bepaal de oude status
    $old_status = $box ? $box->current_status : 'n.v.t.';
    
    // Bepaal de nieuwe status en het wijzigingstype op basis van het formulier
    if ( $form_id === $aanmelden_form_id ) {
         $new_status = 'Aangemeld';
         $modification_type = 'aanmelden';
         $allowed = get_option('esm_allowed_aanmelden', array());
    } elseif ( $form_id === $afmelden_form_id ) {
         $new_status = 'Afgemeld';
         $modification_type = 'afmelden';
         $allowed = get_option('esm_allowed_afmelden', array());
    } else {
         return;
    }
    
    // Controleer of de huidige status het toelaten (optioneel)
    if ( ! in_array( $old_status, $allowed ) ) {
         return;
    }
    
    // Update of insert het record
    if ( $box ) {
         $result = $wpdb->update(
             $table_name,
             array(
                'previous_status' => $old_status,
                'current_status'  => $new_status,
                'last_modified'   => current_time('mysql'),
                'modified_by'     => $modified_by
             ),
             array(
                'stalgang'  => $stalgang,
                'boxnummer' => $boxnummer
             )
         );
    } else {
         $result = $wpdb->insert(
             $table_name,
             array(
                'stalgang'        => $stalgang,
                'boxnummer'       => $boxnummer,
                'current_status'  => $new_status,
                'previous_status' => 'n.v.t.',
                'last_modified'   => current_time('mysql'),
                'modified_by'     => $modified_by
             )
         );
    }
    
    if ( $result !== false ) {
         // Log de wijziging
         esm_log_modification($stalgang, $boxnummer, $new_status, $old_status, $modified_by, $modification_type);
         error_log("Update/insert succesvol voor stalgang: $stalgang, box: $boxnummer. Type: $modification_type");
    } else {
         error_log("Update/insert mislukt voor stalgang: $stalgang, box: $boxnummer");
    }
}
add_action( 'wpcf7_before_send_mail', 'esm_cf7_before_send_mail_handler' );

/**
 * Update-handler voor individuele statuswijziging via CF7.
 *
 * Deze functie wordt aangeroepen via de 'wpcf7_before_send_mail'-actie wanneer het
 * updateformulier wordt ingediend. Hij controleert eerst of de vereiste velden aanwezig
 * zijn en of het ingevoerde wachtwoord overeenkomt met de in de instellingen opgeslagen waarde.
 * Vervolgens wordt er gecontroleerd of er al een record bestaat in de database voor de
 * opgegeven 'stalgang' en 'boxnummer'. Als dat zo is, wordt het record bijgewerkt; zo niet,
 * dan wordt er een nieuw record aangemaakt.
 *
 * @param WPCF7_ContactForm $contact_form De contactform object van CF7.
 */
function esm_cf7_update_handler( $contact_form ) {
    // Haal het CF7 formulier ID voor de update op uit de plugin-instellingen.
    $cf7_update_id = intval( get_option('esm_cf7_update_form_id') );
    // Vergelijk met het ID van het huidige formulier.
    $form_id = intval( $contact_form->id() );
    
    // Als het formulier niet het updateformulier is, stoppen we de verwerking.
    if ( $form_id !== $cf7_update_id ) {
         return;
    }
    
    // Haal de CF7-submission op.
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
         return;
    }
    
    // Verkrijg de door de gebruiker ingevulde data.
    $data = $submission->get_posted_data();
    
    // Zorg ervoor dat alle vereiste velden aanwezig zijn
    if ( empty($data['stal']) || empty($data['box']) || empty($data['new_status']) || empty($data['update_password']) ) {
         error_log("Sommige vereiste velden ontbreken in update handler.");
         return;
    }
    
    // Controleer het wachtwoord
    $expected_password = get_option('esm_update_password');
    // Controleer of het ingevoerde wachtwoord overeenkomt met het verwachte wachtwoord.
    if ( $data['update_password'] !== $expected_password ) {
         error_log("Update wachtwoord komt niet overeen. Ingegeven: " . $data['update_password'] . ", verwacht: " . $expected_password);
         // Stop de verwerking als het wachtwoord onjuist is.
         return;
    }
    
    $stalgang = sanitize_text_field($data['stal']);
    $boxnummer = intval($data['box']);
    if ( is_array( $data['new_status'] ) ) {
        $new_status = sanitize_text_field( $data['new_status'][0] );
    } else {
        $new_status = sanitize_text_field( $data['new_status'] );
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $box = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer) );
    
    error_log("POST data: " . print_r($_POST, true));

    // Voer een update of insert uit afhankelijk van of het record al bestaat.
    if( $box ) {
         // Definieer de oude status
         $old_status = $box->current_status;
         
         $result = $wpdb->update(
             $table_name,
             array(
                'previous_status' => $old_status,
                'current_status'  => $new_status,
                'last_modified'   => current_time('mysql'),
                'modified_by'     => 'admin'
             ),
             array(
                'stalgang'  => $stalgang,
                'boxnummer' => $boxnummer
             )
         );
         if ( $result !== false ) {
             esm_log_modification($stalgang, $boxnummer, $new_status, $old_status, 'admin', 'dashboard');
         }
        
         if ( $result === false ) {
             error_log("Update mislukt voor stalgang: $stalgang, box: $boxnummer");
         } else {
             error_log("Update succesvol voor stalgang: $stalgang, box: $boxnummer");
         }
    } else {
         // Er bestaat nog geen record, dus maak een nieuw record aan.
         $result = $wpdb->insert($table_name, array(
             'stalgang' => $stalgang,
             'boxnummer' => $boxnummer,
             'current_status' => $new_status,
             'previous_status' => 'n.v.t.',
             'last_modified' => current_time('mysql'),
             'modified_by'     => 'admin'
         ));
         if ( $result !== false ) {
             esm_log_modification($stalgang, $boxnummer, $new_status, $old_status, 'admin', 'dashboard');
         }
    }
}
add_action( 'wpcf7_before_send_mail', 'esm_cf7_update_handler' );


// Voeg een script toe dat na een succesvolle CF7 verzending de gebruiker doorstuurt
function esm_cf7_redirect_script() {
    $redirect_page = get_option('esm_redirect_page');
    if ( empty( $redirect_page ) ) {
         return;
    }
    // Haal de relevante formulier ID's op
    $aanmelden_form_id = intval( get_option('esm_cf7_aanmelden_form_id') );
    $afmelden_form_id  = intval( get_option('esm_cf7_afmelden_form_id') );
    // Haal ook de update formulier ID op
    $update_form_id   = intval( get_option('esm_cf7_update_form_id') );
    
    // Als er geen redirect bedoeld is voor de update, gebruik alleen de andere twee
    if ( !$aanmelden_form_id && !$afmelden_form_id ) {
         return;
    }
    $redirect_url = get_permalink( $redirect_page );
    ?>
    <script>
    document.addEventListener('wpcf7mailsent', function( event ) {
         // Redirect alleen als het ingediende formulier niet het updateformulier is.
         var updateFormId = <?php echo $update_form_id; ?>;
         if ( event.detail.contactFormId != updateFormId ) {
             window.location.href = "<?php echo esc_url( $redirect_url ); ?>";
         }
    }, false );
    </script>
    <?php
}
add_action('wp_footer', 'esm_cf7_redirect_script');


function esm_modal_html() {
    ?>
    <!-- Modal HTML-structuur -->
    <div id="esm-modal" class="esm-modal">
      <div class="esm-modal-content">
        <span class="esm-close">&times;</span>
        <div id="esm-modal-body">
          <!-- Bestaande boxdetails -->
          <div id="esm-box-details">
            <!-- Hier komen de boxdetails en de knop "Status wijzigen" -->
          </div>
          <!-- Container voor het updateformulier -->
          <div id="esm-update-container"></div>
        </div>
      </div>
    </div>
    <?php
}
add_action('wp_footer', 'esm_modal_html');

function esm_enqueue_modal_assets() {
    if ( is_admin() ) {
        return;
    }
    wp_enqueue_style( 'esm-modal-style', plugin_dir_url( __FILE__ ) . 'css/esm-modal.css' );
    wp_enqueue_script( 'esm-modal-script', plugin_dir_url( __FILE__ ) . 'js/esm-modal.js', array('jquery'), '1.0', true );
    
    // Bereid de CF7-update form output voor
    $cf7_update_form = do_shortcode("[contact-form-7 id='" . intval(get_option("esm_cf7_update_form_id")) . "']");
    $cf7_update_form = apply_filters('wpcf7_form_elements', $cf7_update_form);
    wp_localize_script('esm-modal-script', 'esm_modal_vars', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'cf7UpdateForm' => do_shortcode("[contact-form-7 id='" . intval(get_option("esm_cf7_update_form_id")) . "']")
//        'cf7UpdateForm' => $cf7_update_form
    ));
}
add_action( 'wp_enqueue_scripts', 'esm_enqueue_modal_assets' );

/**
 * Forceer CF7 om een correcte front-end URL te gebruiken als formulieractie,
 * wanneer het formulier gerenderd wordt in een AJAX-context.
 */
add_filter('wpcf7_form_action_url', 'esm_fix_cf7_form_action_url', 10, 2);
function esm_fix_cf7_form_action_url($url, $contact_form = null) {
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        // Pas de URL aan naar de gewenste front-end pagina (pas indien nodig aan)
        return home_url('/form/');
    }
    return $url;
}

/**
 * Schakel CF7 AJAX uit voor de formulieren die in de shortcode worden gebruikt.
*//*
add_filter('wpcf7_use_ajax', 'esm_disable_cf7_ajax', 10, 2);
function esm_disable_cf7_ajax($use_ajax, $contact_form) {
    // Haal de formulier-ID's op uit de opties
    $aanmelden_id = intval(get_option('esm_cf7_aanmelden_form_id'));
    $afmelden_id  = intval(get_option('esm_cf7_afmelden_form_id'));
    // Als het huidige formulier een van deze is, schakel AJAX uit.
    if ( in_array( $contact_form->id(), array($aanmelden_id, $afmelden_id) ) ) {
        return false;
    }
    return $use_ajax;
}*/

// Validatiefilter voor het veld 'update_password' in CF7
add_filter('wpcf7_validate_text*', 'esm_cf7_custom_validate_update_password', 20, 2);
function esm_cf7_custom_validate_update_password($result, $tag) {
    $tag = new WPCF7_FormTag($tag);
    if ('update_password' == $tag->name) {
        $value = isset($_POST[$tag->name]) ? trim($_POST[$tag->name]) : '';
        $expected_password = get_option('esm_update_password');
        if ($value !== $expected_password) {
            $result->invalidate($tag, "Wachtwoord is onjuist.");
        }
    }
    return $result;
}

add_filter('wpcf7_form_action_url', 'esm_remove_cf7_hash');
function esm_remove_cf7_hash($url) {
    return strtok($url, '#'); // retourneert de URL zonder hash
}


/**
 * AJAX-handler om boxgegevens op te halen.
 * Verwacht GET-parameters: 'stal' en 'box'
 */
function esm_get_box_data_ajax() {
    $stalgang = isset($_GET['stal']) ? sanitize_text_field($_GET['stal']) : '';
    $boxnummer = isset($_GET['box']) ? intval($_GET['box']) : 0;
    
    if ( empty($stalgang) || $boxnummer === 0 ) {
         wp_send_json_error('Ongeldige parameters.');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $box = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer));
    if(!$box){
        $box = (object) array(
            'current_status' => 'n.v.t.',
            'previous_status' => '',
            'last_modified' => '',
            'modified_by' => ''
        );
    }
    
    // Haal de formulier-ID's op (indien nodig voor de logica)
    $cf7_aanmelden_id = get_option('esm_cf7_aanmelden_form_id', '');
    $cf7_afmelden_id  = get_option('esm_cf7_afmelden_form_id', '');
    
    // Controleer of aanmelden/afmelden is toegestaan; in_array retourneert een boolean.
    $allowed_aanmelden = in_array($box->current_status, get_option('esm_allowed_aanmelden', array()));
    $allowed_afmelden  = in_array($box->current_status, get_option('esm_allowed_afmelden', array()));
    
    // Bouw een data-array met de benodigde waarden.
    $data = array(
         'stalgang'       => $stalgang,
         'boxnummer'      => $boxnummer,
         'current_status' => $box->current_status,
         'previous_status'=> $box->previous_status,
         'last_modified'  => $box->last_modified,
         'modified_by'    => $box->modified_by,
         'allowed_aanmelden' => $allowed_aanmelden,
         'allowed_afmelden'  => $allowed_afmelden,
    );
    
    wp_send_json_success($data);
}
add_action('wp_ajax_esm_get_box_data', 'esm_get_box_data_ajax');
add_action('wp_ajax_nopriv_esm_get_box_data', 'esm_get_box_data_ajax');

function esm_enqueue_huurders_assets() {
    if ( is_admin() ) return;
    wp_enqueue_script( 'esm-huurders-script', plugin_dir_url( __FILE__ ) . 'js/esm-huurders.js', array('jquery'), '1.0', true );
    wp_localize_script('esm-huurders-script', 'esm_vars', array(
         'ajaxUrl' => admin_url('admin-ajax.php')
    ));
}
add_action( 'wp_enqueue_scripts', 'esm_enqueue_huurders_assets' );


/**
 * AJAX-handler om de dashboard-data op te halen.
 * Deze functie roept een aparte functie aan die de dashboard HTML genereert.
 */
function esm_get_dashboard_data_ajax() {
    // Roep de functie aan die de dashboard-HTML samenstelt.
    // Als je al een dashboard-renderfunctie hebt (bijv. esm_render_dashboard()),
    // gebruik die dan. Anders kun je hier de huidige code van je dashboard-shortcode neerzetten.
    $html = esm_render_dashboard(); // Zorg dat deze functie de HTML van het dashboard retourneert.
    
    // Zorg ervoor dat er geen outputbuffers met onbedoelde output zijn
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    wp_send_json_success(array('html' => $html));
    exit;
}
add_action('wp_ajax_esm_get_dashboard_data', 'esm_get_dashboard_data_ajax');
add_action('wp_ajax_nopriv_esm_get_dashboard_data', 'esm_get_dashboard_data_ajax');

function esm_render_dashboard() {
    ob_start();
    
    $stallen = get_option('esm_stallen', array());
    $status_colors = get_option('esm_status_colors', array());
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    
    echo '<div class="esm-dashboard" style="display:flex; gap:20px; flex-wrap: wrap;">';
    foreach($stallen as $config){
        $stalgang = $config['stalgang'];
        $box_range = $config['box_range'];
        $kolom = intval($config['kolom']);
        
        list($start, $end) = explode('-', $box_range);
        $start = intval($start);
        $end = intval($end);
        $total_boxes = $end - $start + 1;
        $rows = ceil($total_boxes / $kolom);
        
        echo '<div class="esm-stalgang-container">';
        echo '<h3 class="esm-stalgang-header">Stalgang: ' . esc_html($stalgang) . '</h3>';
        echo '<table class="esm-stalgang-table" border="1" cellspacing="0" cellpadding="5">';
        
        $boxes = range($start, $end);
        $grid = array();
        for ($i = 0; $i < $rows; $i++) {
            $grid[$i] = array();
            for($j = 0; $j < $kolom; $j++){
                $index = $i + $j * $rows;
                $grid[$i][] = isset($boxes[$index]) ? $boxes[$index] : '';
            }
        }

        // Nieuwe aanpassing: Als er 2 kolommen zijn, pas de volgorde van de linkerkolom aan
        if ($kolom == 2) {
            $left_order = get_option('esm_left_column_order', 'normal');
            if ($left_order === 'reversed') {
                 $leftColumn = array();
                 foreach ($grid as $i => $row) {
                     $leftColumn[] = $row[0];
                 }
                 $leftColumn = array_reverse($leftColumn);
                 foreach ($grid as $i => &$row) {
                     $row[0] = $leftColumn[$i];
                 }
                 unset($row);
            }
        }
        
        foreach($grid as $row){
            echo '<tr>';
            foreach($row as $boxnummer){
                if($boxnummer !== ''){
                    $box = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer));
                    if(!$box){
                        $box = (object) array('current_status' => 'n.v.t.');
                    }
                    $bg_color = isset($status_colors[$box->current_status]) ? $status_colors[$box->current_status] : '#ffffff';
                    echo '<td class="esm-box" data-stalgang="' . esc_attr($stalgang) . '" data-boxnummer="' . esc_attr($boxnummer) . '" style="background-color:' . esc_attr($bg_color) . '; cursor:pointer;">' . esc_html($boxnummer) . '</td>';
                } else {
                    echo '<td></td>';
                }
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    echo '</div>';

    return ob_get_clean();
}

function esm_enqueue_dashboard_assets() {
    if ( is_admin() ) return;
    wp_enqueue_script( 'esm-dashboard-script', plugin_dir_url( __FILE__ ) . 'js/esm-dashboard.js', array('jquery'), '1.0', true );
    // Stel het refresh interval in (bijvoorbeeld in milliseconden, standaard 30000 ms)
    wp_localize_script('esm-dashboard-script', 'esm_dashboard_vars', array(
         'ajaxUrl' => admin_url('admin-ajax.php'),
         'refreshInterval' => get_option('esm_dashboard_refresh_interval', '30000')
    ));
}
add_action( 'wp_enqueue_scripts', 'esm_enqueue_dashboard_assets' );


function esm_generate_qrcode_zip_ajax() {
    // Controleer of de gebruiker de juiste rechten heeft.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 'Error', array( 'response' => 403 ) );
    }

    // Zorg dat de phpqrcode library beschikbaar is
    require_once(plugin_dir_path(__FILE__) . 'lib/phpqrcode/qrlib.php');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    
    // Haal alle boxen op, geordend op stalgang en boxnummer
    $boxes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY stalgang, boxnummer");
    
    if ( !$boxes ) {
         wp_die('Geen boxen gevonden.');
    }
    
    // Maak een nieuw ZipArchive-object en een tijdelijk bestand voor de ZIP
    $zip = new ZipArchive();
    $tmp_file = tempnam(sys_get_temp_dir(), 'qr_');
    if ( $zip->open($tmp_file, ZipArchive::OVERWRITE) !== TRUE ) {
         wp_die('Kon ZIP-bestand niet openen.');
    }
    
    // Loop over alle boxen en voeg de gegenereerde QR-code PNG toe
    foreach ($boxes as $box) {
         $stalgang = $box->stalgang;
         $boxnummer = $box->boxnummer;
         $qrcode_file = esm_generate_box_qrcode($stalgang, $boxnummer);
         if ( file_exists($qrcode_file) ) {
             // Geef een nette bestandsnaam, bv. "Stalgang-A_Box-7.png"
             $zip_filename = 'Stalgang-' . $stalgang . '_Box-' . $boxnummer . '.png';
             $zip->addFile($qrcode_file, $zip_filename);
         }
    }
    $zip->close();
    
    // Zorg dat eventuele outputbuffers leeg zijn
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Verstuur de juiste headers zodat de browser de download forceert
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="qr_codes.zip"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tmp_file));
    readfile($tmp_file);
    unlink($tmp_file); // Verwijder het tijdelijke bestand
    exit;
}
add_action('wp_ajax_esm_generate_qrcode_zip', 'esm_generate_qrcode_zip_ajax');

function esm_generate_box_qrcode($stalgang, $boxnummer) {
    // Bepaal het pad naar de phpqrcode-library
    $qr_lib_path = plugin_dir_path(__FILE__) . 'lib/phpqrcode/qrlib.php';
    
    if ( ! file_exists($qr_lib_path) ) {
         error_log('PHPQRCode library niet gevonden op: ' . $qr_lib_path);
         return false;
    }
    
    require_once($qr_lib_path);
    
    if ( ! defined('QR_ECLEVEL_L') ) {
         error_log('QR_ECLEVEL_L constant is niet gedefinieerd. Controleer de phpqrcode library.');
         return false;
    }

    // Haal de QR-code redirect pagina op uit de instellingen.
    $qr_redirect_page = get_option('esm_qr_redirect_page');
    if ($qr_redirect_page) {
        $base_url = get_permalink($qr_redirect_page);
    } else {
        $base_url = home_url('/');
    }
    
    // Verbind met de database om de laatste wijziging voor deze box op te halen
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $box_data = $wpdb->get_row($wpdb->prepare("SELECT last_modified FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer));
    
   // Bouw de URL met de GET parameters voor deze box.
    $data = $base_url . "?stal=" . urlencode($stalgang) . "&box=" . urlencode($boxnummer);

    // Maak een tijdelijk bestand voor de QR-code
    $tempDir = sys_get_temp_dir();
    $filename = $tempDir . '/qrcode_' . $stalgang . '_' . $boxnummer . '.png';
    
    // Genereer de QR-code en sla deze op in het tijdelijke bestand
    QRcode::png($data, $filename, QR_ECLEVEL_L, 4);
    
    return $filename;
}

/**
 * Logt een wijziging in de box.
 *
 * @param string $stalgang De stalgang.
 * @param int $boxnummer Het boxnummer.
 * @param string $new_status De nieuwe status.
 * @param string $old_status De oude status.
 * @param string $modified_by Wie de wijziging heeft uitgevoerd (bijv. 'admin').
 * @param string $modification_type Het type wijziging ('aanmelden', 'afmelden', 'dashboard' of 'bulk edit').
 */
function esm_log_modification($stalgang, $boxnummer, $new_status, $old_status, $modified_by, $modification_type) {
    error_log("Update succesvol voor stalgang: $stalgang, box: $boxnummer");

    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_log';
    
    $wpdb->insert($table_name, array(
         'stalgang' => $stalgang,
         'boxnummer' => $boxnummer,
         'new_status' => $new_status,
         'old_status' => $old_status,
         'datetime' => current_time('mysql'),
         'modified_by' => $modified_by,
         'modification_type' => $modification_type
    ));
}

function esm_render_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_log';

    // Haal alle logs op (voor een eenvoudig voorbeeld zonder paginering)
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY datetime DESC");

    ?>
    <div class="wrap">
        <h1>Wijzigingslog</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Stalgang</th>
                    <th>Boxnummer</th>
                    <th>Nieuwe status</th>
                    <th>Oude status</th>
                    <th>Datum/Tijd</th>
                    <th>Gewijzigd door</th>
                    <th>Type wijziging</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html($log->stalgang); ?></td>
                            <td><?php echo esc_html($log->boxnummer); ?></td>
                            <td><?php echo esc_html($log->new_status); ?></td>
                            <td><?php echo esc_html($log->old_status); ?></td>
                            <td><?php echo esc_html($log->datetime); ?></td>
                            <td><?php echo esc_html($log->modified_by); ?></td>
                            <td><?php echo esc_html($log->modification_type); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Geen logs gevonden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
