<?php
/*
Plugin Name: EventStableManager
Description: Plugin voor het beheer van een paardenstal tijdens een evenement. Deze versie gebruikt CF7-formulieren voor aanmelden en afmelden.
Version: 3.0.2
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
                  $wpdb->update($table_name, array(
                      'previous_status' => $existing->current_status,
                      'current_status' => $new_status,
                      'last_modified' => current_time('mysql')
                  ), array(
                      'stalgang' => $stalgang,
                      'boxnummer' => $i
                  ));
             } else {
                  $wpdb->insert($table_name, array(
                      'stalgang' => $stalgang,
                      'boxnummer' => $i,
                      'current_status' => $new_status,
                      'previous_status' => 'n.v.t.',
                      'last_modified' => current_time('mysql')
                  ));
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
        // Loop door de stallen en gebruik de sleutel 'stalgang' als de waarde
        foreach ( $stallen as $row ) {
            // Controleer of 'stalgang' bestaat in de rij
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
        
        // CF7 Formulier ID's voor de acties
        $cf7_aanmelden = isset($_POST['esm_cf7_aanmelden_form_id']) ? sanitize_text_field($_POST['esm_cf7_aanmelden_form_id']) : '';
        update_option('esm_cf7_aanmelden_form_id', $cf7_aanmelden);
        $cf7_afmelden = isset($_POST['esm_cf7_afmelden_form_id']) ? sanitize_text_field($_POST['esm_cf7_afmelden_form_id']) : '';
        update_option('esm_cf7_afmelden_form_id', $cf7_afmelden);
        $cf7_update = isset($_POST['esm_cf7_update_form_id']) ? sanitize_text_field($_POST['esm_cf7_update_form_id']) : '';
        update_option('esm_cf7_update_form_id', $cf7_update);

        $update_password = isset($_POST['esm_update_password']) ? sanitize_text_field($_POST['esm_update_password']) : '';
        update_option('esm_update_password', $update_password);

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
    
    $stallen = get_option('esm_stallen', array());
    $status_colors = get_option('esm_status_colors', array());
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    
    echo '<div class="esm-dashboard" style="display:flex; gap:20px;">';
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
add_shortcode('eventstable_dashboard', 'esm_dashboard_shortcode');

// ----- HUURDERS SHORTCODE -----
// Toont de boxdetails op basis van de GET-parameters en geeft, afhankelijk van de huidige status,
// het bijbehorende CF7 formulier weer (aanmelden of afmelden).
function esm_huurders_shortcode() {
    ob_start();
    ?>
    <div id="esm-huurders-content">
         <!-- Container voor de boxgegevens, die via AJAX ingevuld wordt -->
         <div id="box-details">
             <p>Bezig met laden…</p>
         </div>
         <!-- CF7 formulieren; standaard verborgen -->
         <div id="cf7-aanmelden" style="display: none;">
             <?php echo do_shortcode('[contact-form-7 id="' . intval(get_option('esm_cf7_aanmelden_form_id')) . '"]'); ?>
         </div>
         <div id="cf7-afmelden" style="display: none;">
             <?php echo do_shortcode('[contact-form-7 id="' . intval(get_option('esm_cf7_afmelden_form_id')) . '"]'); ?>
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
    $aanmelden_form_id = intval( get_option('esm_cf7_aanmelden_form_id') );
    $afmelden_form_id  = intval( get_option('esm_cf7_afmelden_form_id') );
    $form_id = intval( $contact_form->id() );
    
    // Alleen verwerken als het formulier een van onze is
    if ( $form_id !== $aanmelden_form_id && $form_id !== $afmelden_form_id ) {
         return;
    }
    
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
         return;
    }
    
    $data = $submission->get_posted_data();
    // Verwacht de verborgen velden: 'stal' en 'box'
    if ( empty($data['stal']) || empty($data['box']) ) {
         return;
    }
    
    $stalgang = sanitize_text_field($data['stal']);
    $boxnummer = intval($data['box']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    $box = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer) );
    
    // Als er nog geen record bestaat, initialiseer deze als 'n.v.t.'
    if ( ! $box ) {
         $box = (object) array('current_status' => 'n.v.t.');
         // Insert een nieuw record met de nieuwe status
         $is_new = true;
    } else {
         $is_new = false;
    }
    
    if ( $form_id === $aanmelden_form_id ) {
         $new_status = 'Aangemeld';
         $allowed = get_option('esm_allowed_aanmelden', array());
         if ( ! in_array($box->current_status, $allowed) ) {
             return;
         }
    } elseif ( $form_id === $afmelden_form_id ) {
         $new_status = 'Afgemeld';
         $allowed = get_option('esm_allowed_afmelden', array());
         if ( ! in_array($box->current_status, $allowed) ) {
             return;
         }
    }
    
    // Gebruik 'your-name' omdat "name" gereserveerd is
    $name  = isset($data['your-name']) ? sanitize_text_field($data['your-name']) : '';
    $horse = isset($data['horse'])      ? sanitize_text_field($data['horse']) : '';
    $phone = isset($data['phone'])      ? sanitize_text_field($data['phone']) : '';
    
    if ( $is_new ) {
         // Record bestaat nog niet, dus maak een nieuw record aan
         $wpdb->insert(
              $table_name,
              array(
                   'stalgang'      => $stalgang,
                   'boxnummer'     => $boxnummer,
                   'current_status'=> $new_status,
                   'previous_status'=> $box->current_status, // dit zal 'n.v.t.' zijn
                   'last_modified' => current_time('mysql'),
                   'modified_by'   => $name . ' (Paard: ' . $horse . ', Tel: ' . $phone . ')'
              ),
              array('%s', '%d', '%s', '%s', '%s', '%s')
         );
    } else {
         // Record bestaat wel, dus werk deze bij
         $wpdb->update(
              $table_name,
              array(
                   'previous_status' => $box->current_status,
                   'current_status'  => $new_status,
                   'last_modified'   => current_time('mysql'),
                   'modified_by'     => $name . ' (Paard: ' . $horse . ', Tel: ' . $phone . ')'
              ),
              array(
                   'stalgang'  => $stalgang,
                   'boxnummer' => $boxnummer
              ),
              array('%s', '%s', '%s', '%s'),
              array('%s', '%d')
         );
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
    
    // Controleer of alle vereiste velden aanwezig zijn.
    if ( empty($data['stal']) || empty($data['box']) || empty($data['new_status']) || empty($data['update_password']) ) {
         error_log("Sommige vereiste velden ontbreken in update handler.");
         return;
    }
    
    // Haal het verwachte wachtwoord op uit de plugin-instellingen.
    $expected_password = get_option('esm_update_password');
    // Controleer of het ingevoerde wachtwoord overeenkomt met het verwachte wachtwoord.
    if ( $data['update_password'] !== $expected_password ) {
         error_log("Update wachtwoord komt niet overeen. Ingegeven: " . $data['update_password'] . ", verwacht: " . $expected_password);
         // Stop de verwerking als het wachtwoord onjuist is.
         return;
    }
    
    // Zorg dat 'new_status' een string is. Soms komt dit veld als array binnen.
    if ( is_array( $data['new_status'] ) ) {
        $new_status = sanitize_text_field( $data['new_status'][0] );
    } else {
        $new_status = sanitize_text_field( $data['new_status'] );
    }
    
    // Sanitize de 'stal' waarde en converteer 'box' naar een integer.
    $stalgang = sanitize_text_field($data['stal']);
    $boxnummer = intval($data['box']);
    
    // Verbind met de database en stel de tabelnaam in.
    global $wpdb;
    $table_name = $wpdb->prefix . 'eventstable_manager';
    
    // Zoek naar een bestaand record voor deze stalgang en dit boxnummer.
    $box = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE stalgang = %s AND boxnummer = %d", $stalgang, $boxnummer) );
    
    // Voer een update of insert uit afhankelijk van of het record al bestaat.
    if( $box ) {
         // Update het bestaande record: sla de huidige status op als 'previous_status' en werk de status bij.
         $result = $wpdb->update(
             $table_name,
             array(
                'previous_status' => $box->current_status,
                'current_status'  => $new_status,
                'last_modified'   => current_time('mysql')
             ),
             array(
                'stalgang'  => $stalgang,
                'boxnummer' => $boxnummer
             )
         );
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
             'last_modified' => current_time('mysql')
         ));
         if ( $result === false ) {
             error_log("Insert mislukt voor stalgang: $stalgang, box: $boxnummer");
         } else {
             error_log("Insert succesvol voor stalgang: $stalgang, box: $boxnummer");
         }
    }
}
// Koppel de functie aan de CF7 'wpcf7_before_send_mail' actie.
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
        'cf7UpdateForm' => $cf7_update_form
    ));
}
add_action( 'wp_enqueue_scripts', 'esm_enqueue_modal_assets' );

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
    
    ob_start();
    ?>
    <div class="esm-huurders">
       <h2>Box Details</h2>
       <p><strong>Stalgang:</strong> <?php echo esc_html($stalgang); ?></p>
       <p><strong>Boxnummer:</strong> <?php echo esc_html($boxnummer); ?></p>
       <p><strong>Huidige status:</strong> <?php echo esc_html($box->current_status); ?></p>
       <p><strong>Vorige status:</strong> <?php echo esc_html($box->previous_status); ?></p>
       <p><strong>Laatste wijziging:</strong> <?php echo esc_html($box->last_modified); ?></p>
       <p><strong>Gewijzigd door:</strong> <?php echo esc_html($box->modified_by); ?></p>
       <?php
       // Bepaal welke actie beschikbaar is
       $allowed_aanmelden = in_array($box->current_status, get_option('esm_allowed_aanmelden', array()));
       $allowed_afmelden = in_array($box->current_status, get_option('esm_allowed_afmelden', array()));
       if($allowed_aanmelden){
           echo '<p>Aanmelden beschikbaar.</p>';
       } elseif($allowed_afmelden){
           echo '<p>Afmelden beschikbaar.</p>';
       } else {
           echo '<p>Geen actie beschikbaar voor de huidige status.</p>';
       }
       ?>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(array('html' => $html));
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

