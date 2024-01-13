(() => {
  selector = "#csv-importer-app";

  const el = document.querySelector(selector);

  if (!el) return console.log(`${selector} not found`);

  const { createApp } = Vue;

  const csvImporterApp = createApp({
    data() {
      return {
        currentStep: 1,
        totalRows: 0,
        status: "idle",
        logs: [],
        limitItems: 0,
        csvData: [],
      };
    },
    methods: {
      uploadFile() {
        this.status = "idle";
        const fileInput = document.querySelector('input[name="csv_file"]');
        if (fileInput.files.length > 0) {
          const reader = new FileReader();
          reader.onload = e => {
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
                this.status = error.message;
              },
            });
          };
          reader.readAsText(fileInput.files[0]);
        } else {
          this.status = "No file selected.";
        }
      },
      startImport() {
        this.logs = [];
        this.status = "processing";
        this.csvData.forEach((row, index) => {
          // Send AJAX request for each row
          jQuery.ajax({
            type: "POST",
            url: ajax.url,
            data: {
              nonce: ajax.nonce,
              action: "start_csv_import",
              data: {
                csvRow: row,
              },
            },
            success: response => {
              const log = response.data.log;
              // Update logs in real-time
              this.logs.push(log);
              this.status = response.status || "success";
            },
            error: (XMLHttpRequest, textStatus, errorThrown) => {
              // Handle errors
            },
            timeout: 60000,
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
        this.status = "idle";
        this.logs = [];
        this.csvData = [];
      },
    },
  });

  csvImporterApp.mount(selector);
})();
