<?php
/*
Plugin Name: Multi Plugin Uploader
Description: Upload and install multiple plugin ZIP files simultaneously
Version: 1.0
Author: Ricardo Christovão da Silva
*/

if (!defined('ABSPATH')) exit;

class MultiPluginUploader {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('wp_ajax_process_plugin_uploads', array($this, 'process_plugin_uploads'));
    }

    public function add_plugin_page() {
        add_submenu_page(
            'plugins.php',
            'Upload Multiple Plugins',
            'Upload Multiple',
            'install_plugins',
            'plugin-multi-uploader',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        if (!current_user_can('install_plugins')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1>Upload Multiple Plugins</h1>
            
            <div style="border: 2px dashed #b4b9be; padding: 20px; text-align: center; margin: 20px 0; background: #fff;">
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('plugin_upload_nonce'); ?>
                    <input type="file" name="plugin_files[]" multiple accept=".zip">
                    <p>Selecione múltiplos arquivos .zip de plugins</p>
                    <input type="submit" class="button button-primary" value="Instalar Plugins">
                </form>
            </div>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['plugin_files'])) {
                $this->process_uploads();
            }
            ?>
        </div>
        <?php
    }

    private function process_uploads() {
        check_admin_referer('plugin_upload_nonce');
        
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        foreach ($_FILES['plugin_files']['tmp_name'] as $index => $tmp_name) {
            $file_name = $_FILES['plugin_files']['name'][$index];
            
            if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'zip') {
                echo "<div style='color: red; padding: 10px;'>
                    $file_name: Arquivo inválido. Apenas arquivos ZIP são permitidos.
                </div>";
                continue;
            }

            $temp_file = wp_tempnam($file_name);
            if (move_uploaded_file($tmp_name, $temp_file)) {
                $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
                $installed = $upgrader->install($temp_file);

                if (!is_wp_error($installed) && $installed) {
                    $plugin_file = $upgrader->plugin_info();
                    activate_plugin($plugin_file);
                    echo "<div style='color: green; padding: 10px;'>
                        $file_name: Plugin instalado e ativado com sucesso
                    </div>";
                } else {
                    $error_message = is_wp_error($installed) ? 
                        $installed->get_error_message() : 
                        'Falha na instalação';
                    echo "<div style='color: red; padding: 10px;'>
                        $file_name: $error_message
                    </div>";
                }
                @unlink($temp_file);
            }
        }
    }
}

new MultiPluginUploader();