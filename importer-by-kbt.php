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

            <div v-if="currentStep === 1">
                <input type="file" name="csv_file" accept=".csv" />
                <button type="button" class="button-primary upload" @click="uploadFile">Upload</button>
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
            <p>Status: {{ importStatus }}</p>
            <div id="log-container">
                <b v-for="log in logs" :key="log.id">{{ log.message }}</b>
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
                    importStatus: 'Idle',
                    logs: [],
                    limitItems: 0,
                    csvData: []
                };
            },
            methods: {
                uploadFile() {
                    const fileInput = document.querySelector('input[name="csv_file"]');

                    if (fileInput.files.length > 0) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const csvContent = e.target.result;
                            const rows = csvContent.split('\n').filter(row => row.trim() !== '');


                            this.totalRows = rows.length - 1; // Exclude the header row
                            this.limitItems = this.totalRows;

                            this.csvData = rows.slice(1).map(row => {
                                const values = row.split(',');
                                return Object.fromEntries(values.map((value, i) => [values[0].trim(), value.trim()]));
                            });

                            console.log()

                            this.currentStep = 2; // Move to the next step
                        };
                        reader.readAsText(fileInput.files[0]);
                    } else {
                        console.error('No file selected.');
                    }
                },
                startImport() {
                    jQuery.ajax({
                        type: "POST",
                        url: ajax.url,
                        data: {
                            nonce: ajax.nonce,
                            action: 'start_csv_import',
                            data: {
                                limitItems: this.limitItems,
                                csvData: this.csvData,
                            },
                        },
                        success: function (response) {
                            console.log(response)
                            
                            //Success
                        },
                        error: function (XMLHttpRequest, textStatus, errorThrown) {
                            //Error
                        },
                        timeout: 60000
                    });
                },
                submitForm() {
                    // Handle form submission if needed
                },
                backToUpload() {
                    this.currentStep = 1;
                    this.totalRows = 0;
                    this.limitItems = 0;
                    this.importStatus = 'Idle';
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

    $data = $_POST['data'];


    wp_send_json_success(array(
        'data' => $data,
    ));

    wp_die();
}

add_action('wp_ajax_start_csv_import', 'start_csv_import');
add_action('wp_ajax_nopriv_start_csv_import', 'start_csv_import');