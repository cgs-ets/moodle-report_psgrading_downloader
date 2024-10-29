// import { Grid } from "gridjs";

define(['core/ajax'], function (Ajax) {

    function init() {
        let usernames = [];
        let selectedActivities = JSON.parse(document.querySelector('.psgrading-downloader-table').getAttribute('data-activities-json'));;
        (new Controls(usernames, selectedActivities)).main();
    }

    function Controls(usernames, selectedActivities) {
        let self = this;

        self.usernames = usernames;
        self.selectedActivities = selectedActivities;
    }

    Controls.prototype.main = function () {
        let self = this;

        self.selectSetting();
        const submitBtn = document.querySelector('.btn-psgrading-download');
        submitBtn.addEventListener('click', self.validateForm);

    };

    Controls.prototype.selectSetting = function () {
        let self = this;
        let table = document.querySelector('.psgrading-downloader-table');
        if (table) {
            Array.from(table.rows).forEach(row => {
                const checkbox = (row.cells[0].firstChild)

                if (checkbox.getAttribute('name') != 'select_all') {
                    checkbox.addEventListener('click', self.selectByOneHandler.bind(self, this))
                } else {
                    checkbox.addEventListener('click', self.selectAllHandler.bind(self, this))
                }
            })
        }
    };

    Controls.prototype.selectByOneHandler = function (s, e) {
        let username = e.target.getAttribute('value');
        document.getElementById('psgrading-downloader-danger-alert').classList.add('psgrading-downloader-alert');
        if (e.target.checked) {
            s.addUserToList(username);
        } else {
            s.removeUserFromList(username);
        }
    };

    Controls.prototype.addUserToList = function (username) {
        let selectedusers = document.querySelector('input[name="selectedusers"]').value;

        selectedusers = JSON.parse(selectedusers);
        selectedusers.push(username);
        document.querySelector('input[name="selectedusers"]').value = JSON.stringify(selectedusers);
    }

    Controls.prototype.removeUserFromList = function (username) {
        let selectedusers = JSON.parse(document.querySelector('input[name="selectedusers"]').value);
        selectedusers.splice(selectedusers.indexOf(username), 1);
        document.querySelector('input[name="selectedusers"]').value = JSON.stringify(selectedusers);
    };

    Controls.prototype.selectAllHandler = function (s, e) {

        const inputs = document.querySelectorAll('input[name="select_student[]"]');
        document.getElementById('psgrading-downloader-danger-alert').classList.add('psgrading-downloader-alert');

        if (e.target.checked) {
            document.querySelector('input[name="selectedusers"]').value = document.querySelector('.psgrading-downloader-table').getAttribute('data-all-users');
        } else {
            document.querySelector('input[name="selectedusers"]').value = '[]';
        }

        inputs.forEach(input => {
            input.checked = !input.checked;
        });

    };

    Controls.prototype.validateForm = function (e) {
        e.preventDefault();

        // document.getElementById('psgrading-downloader-warning-alert').classList.remove('psgrading-downloader-warning-alert');

        if (document.querySelector('input[name="selectedusers"]').value === "[]") {
            document.getElementById('psgrading-downloader-danger-alert').classList.remove('psgrading-downloader-alert');
        } else {
            document.querySelector(".download-report-form").submit();
        }


    }

    return {
        init: init
    };
});


