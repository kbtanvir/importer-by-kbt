<?php
/*
Plugin Name: Importer by KBT
Description: A WordPress plugin for importing posts from a CSV file
Version: 1.1
Author: K. B. Tanvir
*/

function enqueue_csv_importer_script()
{
    wp_enqueue_script('vue-js', 'https://cdn.jsdelivr.net/npm/vue@2.6.14', array(), '2.6.14', false);
    wp_enqueue_script('axios', 'https://cdn.jsdelivr.net/npm/axios@0.21.1', array('jquery'), '0.21.1', true);

    // Pass the ajaxurl and nonce to the script
    wp_localize_script('axios', 'csv_importer_data', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('csv_import_nonce')
    ));
}

add_action('admin_enqueue_scripts', 'enqueue_csv_importer_script');

function csv_importer_admin_page()
{
    ?>
    <div class="wrap" id="csv-importer-app">
        <h2>Importer by KBT - MultiStep Form</h2>

        <form id="csv-import-form" @submit.prevent="submitForm">
            <?php wp_nonce_field('csv_import_nonce', 'csv_import_nonce'); ?>

            <div v-if="currentStep === 1">
                <input type="file" name="csv_file" accept=".csv" />
                <button type="button" class="button-primary" @click="uploadFile">Upload</button>
            </div>

            <div v-if="currentStep === 2">
                <p>Total Rows in CSV: {{ totalRows }}</p>
                <label>Limit Number of Items to Upload:</label>
                <input type="number" v-model="limitItems" :max="totalRows" />
                <button type="button" class="button-primary" @click="startImport">Import</button>
                <button type="button" @click="backToUpload">Back</button>
            </div>
        </form>

        <div id="status-update">
            <p>Total Rows: {{ totalRows }}</p>
            <p>Status: {{ importStatus }}</p>
            <div id="log-container">
                <div v-for="log in logs" :key="log.id">{{ log.message }}</div>
            </div>
        </div>
    </div>

    <script>
        var csvImporterApp = new Vue({
            el: '#csv-importer-app',
            data: {
                currentStep: 1,
                totalRows: 0,
                importStatus: 'Idle',
                logs: [],
                limitItems: 0,
            },
            methods: {
                uploadFile: function () {
                    // Implement the file upload logic

                    // Get the input element for file upload
                    const fileInput = document.querySelector('input[name="csv_file"]');

                    // Check if a file is selected
                    if (fileInput.files.length > 0) {
                        const formData = new FormData();
                        formData.append('action', 'process_csv_file');
                        formData.append('csv_import_nonce', csv_importer_data.nonce);
                        formData.append('csv_file', fileInput.files[0]);

                        // Show loading or processing indicator if needed

                        // Send an AJAX request to process the CSV file
                        axios.post(csv_importer_data.ajaxurl, formData)
                            .then(response => {
                                // Assuming the server responds with the totalRows
                                const csvData = response.data.data.csvData;

                                // Parse the CSV data to count only valid rows
                                const rows = csvData.split('\n');
                                let validRows = 0;

                                for (const row of rows) {
                                    // Ignore empty rows and the header row
                                    if (row.trim() !== '' && !row.includes('header_keyword')) {
                                        validRows++;
                                    }
                                }

                                this.totalRows = validRows;

                                // Show the back button and update currentStep
                                this.currentStep = 2;
                            })
                            .catch(error => {
                                console.error(error);
                                // Handle errors if needed
                            })
                            .finally(() => {
                                // Hide loading or processing indicator if needed
                            });
                    } else {
                        // Handle case where no file is selected
                        console.error('No file selected.');
                    }
                },

                startImport: function () {
                    // Implement the import logic based on the limitItems
                    // Update importStatus and logs as individual items are updated
                },
                submitForm: async function () {
                    // Handle form submission if needed
                },
                backToUpload: function () {
                    // Reset the form to step 1
                    this.currentStep = 1;

                    // Clear any data related to the previous steps
                    this.totalRows = 0;
                    this.limitItems = 0;
                    this.importStatus = 'Idle';
                    this.logs = [];
                },
            }
        });
    </script>
    <?php
}

function csv_importer_menu()
{
    add_menu_page('CSV Importer', 'CSV Importer', 'manage_options', 'csv-importer', 'csv_importer_admin_page');
}

add_action('admin_menu', 'csv_importer_menu');

function process_csv_file()
{
    check_admin_referer('csv_import_nonce', 'csv_import_nonce');

    $csv_file = $_FILES['csv_file'];

    if ($csv_file['error'] == 0)
    {
        $csv_data = file_get_contents($csv_file['tmp_name']);
        $rows     = explode("\n", $csv_data);

        $totalRows = count($rows);

        wp_send_json_success(array(
            'totalRows' => $totalRows
        ));
    }

    // Handle errors or empty data
    wp_send_json_error('Error processing CSV file.');
}

add_action('wp_ajax_process_csv_file', 'process_csv_file');
