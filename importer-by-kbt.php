<?php
/*
Plugin Name: Importer by KBT
Description: A WordPress plugin for importing posts from a CSV file
Version: 1.4
Author: K. B. Tanvir
*/

function add_scripts()
{
    wp_enqueue_script('vue-js', 'https://cdn.jsdelivr.net/npm/vue@3.2.20', array(), '3.2.20', false);
    wp_enqueue_script('papaparse', 'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js', array(), '5.3.0', false);

    wp_localize_script('jquery', 'ajax', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('csv_import_nonce'),
    ));
}

add_action('admin_enqueue_scripts', 'add_scripts');

function csv_importer_admin_page()
{
    ?>
    <div class="wrap" id="csv-importer-app">
        <h2>Importer by KBT - MultiStep Form</h2>

        <form id="csv-import-form" @submit.prevent="submitForm">
            <?php wp_nonce_field('csv_import_nonce', 'csv_import_nonce'); ?>

            <div>
                <!-- <div v-if="currentStep === 1"> -->
                <input type="file" name="csv_file" accept=".csv" />
                <button type="button" class="button-primary upload" @click="uploadFile">Upload</button>
            </div>

            <div v-if="currentStep === 2 && status ==='idle'">

                <p>Total Rows in CSV: {{ totalRows }}</p>
                <label>Limit Number of Items to Upload:</label>
                <input type="number" v-model="limitItems" :max="totalRows" />
                <button type="button" class="button-primary" @click="startImport">Import</button>
                <button type="button" @click="backToUpload">Back</button>
            </div>
        </form>

        <div id="status-update">
            <p>Status: <b style="background-color: yellow;">{{ status }}</b></p>
            <div id="log-container">
                <p v-for="log in logs" :key="log.id"> {{ log.message }}</p>
            </div>
        </div>
    </div>


    <script>
        const { createApp, ref } = Vue;

        const csvImporterApp = createApp({
            data() {
                return {
                    currentStep: 1,
                    totalRows: 0,
                    status: 'idle',
                    logs: [],
                    limitItems: 0,
                    csvData: []
                };
            },
            methods: {
                uploadFile() {
                    this.status = 'idle'
                    const fileInput = document.querySelector('input[name="csv_file"]');
                    if (fileInput.files.length > 0) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const csvContent = e.target.result;
                            Papa.parse(csvContent, {
                                header: true,
                                skipEmptyLines: true,
                                dynamicTyping: true,
                                complete: results => {
                                    this.csvData = results.data;
                                    this.totalRows = this.csvData.length;
                                    this.limitItems = this.totalRows;
                                    this.currentStep = 2;
                                },
                                error: error => {
                                    this.status = error.message
                                },
                            });

                        };
                        reader.readAsText(fileInput.files[0]);
                    } else {
                        this.status = "No file selected."
                    }
                },
                startImport() {
                    this.logs = []
                    this.status = 'processing'
                    this.csvData.forEach((row, index) => {
                        // Send AJAX request for each row
                        jQuery.ajax({
                            type: "POST",
                            url: ajax.url,
                            data: {
                                nonce: ajax.nonce,
                                action: 'start_csv_import',
                                data: {
                                    csvRow: row,
                                },
                            },
                            success: (response) => {
                                const log = response.data.log;
                                // Update logs in real-time
                                this.logs.push(log);
                                this.status = response.status || 'success';
                            },
                            error: (XMLHttpRequest, textStatus, errorThrown) => {
                                // Handle errors
                            },
                            timeout: 60000
                        });

                    });




                },
                submitForm() {
                    // Handle form submission if needed
                },
                backToUpload() {
                    this.currentStep = 1;
                    this.totalRows = 0;
                    this.limitItems = 0;
                    this.status = 'idle';
                    this.logs = [];
                    this.csvData = []
                },
            },
        });

        csvImporterApp.mount('#csv-importer-app');
    </script>
    <?php
}

function add_admin_menu_page()
{
    add_menu_page('CSV Importer', 'CSV Importer', 'manage_options', 'csv-importer', 'csv_importer_admin_page');
}

add_action('admin_menu', 'add_admin_menu_page');

function start_csv_import()
{
    if (!wp_verify_nonce($_POST['nonce'], 'csv_import_nonce'))
    {
        die('Permission Denied.');
    }

    $csv_row   = $_POST['data']['csvRow'];
    $csv_id    = $csv_row['id'];
    $csv_name  = $csv_row['name'];
    $image_url = $csv_row['image_url'];

    $post = get_posts(array(
        'meta_key' => '_uid',
        'meta_value' => $csv_id,
        'post_type' => 'job_listing',
        'post_status' => 'any',
        'numberposts' => 1,
    ));

    if ($post)
    {
        $wp_post_id = $post[0]->ID;

        // Show processing message, logging part needs modification
        $log = array('id' => $csv_id, 'message' => "CSV: $csv_id - $csv_name || WP: {$wp_post_id} \n");

        // Call function to process images
        $image_upload_res = process_image($image_url, $csv_id);



        // Update featured image of post by $wp_post_id
        if (!empty($image_upload_res['$attachment_id']))
        {
            $attachment_url = wp_get_attachment_url($image_upload_res['$attachment_id']);

            update_post_meta($wp_post_id, '_job_cover', $attachment_url);

            $log['message'] .= ' || Featured image updated';
        }
        else
        {
            $log['message'] .= " || {$image_upload_res['message']}";
        }

        // Show update or failed message
        // For now, we are sending logs and status as a JSON response
        wp_send_json_success(array('log' => $log));
    }
    else
    {
        // Post not found, log and handle accordingly
        $log = array('id' => $csv_id, 'message' => "CSV: $csv_id - $csv_name || WP: post not found");

        // Send log as a JSON response
        wp_send_json_success(array('log' => $log));
    }
}

add_action('wp_ajax_start_csv_import', 'start_csv_import');
add_action('wp_ajax_nopriv_start_csv_import', 'start_csv_import');
function process_image($image_url, $csv_id)
{
    // Download image, add csv_id as a prefix before the image file name
    $image_filename = sanitize_file_name($csv_id . basename($image_url));

    // Upload to media gallery
    $upload_dir = wp_upload_dir();
    $image_path = $upload_dir['path'] . '/' . $image_filename;

    // Check if the file already exists in the media library
    $existing_attachment_id = attachment_exists_by_title(sanitize_file_name($csv_id));

    if ($existing_attachment_id)
    {
        // Image already exists, log a message and return
        $log_message = "Image with filename '$image_filename' already exists in the media library.";
        return array('message' => $log_message);
    }


    // If the file doesn't exist, download and save it
    $image_data = file_get_contents($image_url);
    file_put_contents($image_path, $image_data);

    $file_type = wp_check_filetype($image_filename, null);

    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => sanitize_file_name($csv_id),
        'post_content' => '',
        'post_status' => 'inherit',
    );

    $attachment_id = wp_insert_attachment($attachment, $image_path);

    $attachment_meta_data = wp_generate_attachment_metadata($attachment_id, $image_path);

    wp_update_attachment_metadata($attachment_id, $attachment_meta_data);



    // Cleanup downloaded image (optional)

    // Return image ID from the gallery along with log message
    return array('attach_id' => $attachment_id, 'message' => 'Image uploaded successfully.');
}

// Helper function to check if an attachment with a specific title already exists
function attachment_exists_by_title($title)
{
    global $wpdb;
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'attachment'", $title));
    return isset($attachment[0]) ? $attachment[0] : false;
}
