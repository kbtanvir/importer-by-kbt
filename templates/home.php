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
        <p>Status: <b style="background-color: yellow; text-transform: uppercase; padding:5px 20px;">{{ status }}</b>
        </p>
        <div id="log-container" style="overflow-y: scroll; height: 200px; border:1px solid; padding: 10px;">
            <p v-for="log in logs" :key="log.id"> {{ log.message }}</p>
        </div>
    </div>
</div>


<script>
 
</script>