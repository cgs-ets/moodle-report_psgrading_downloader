define(['core/ajax', 'core/url'], function (Ajax, URL) {

    let startTime;
    function init(taskid, courseid) {
        startTime = new Date();
        checkTaskStatus(taskid, courseid);
    }

    function checkTaskStatus(taskid, courseid) {


        console.log("checking...");
        Ajax.call([{
            methodname: "report_psgrading_downloader_check_task_status",
            args: {
                taskid: taskid
            },
            done: function (response) {

                if (response.status === 'complete') {
                    const link = URL.relativeUrl('/report/psgrading_downloader/download_reports.php', {
                        taskid: taskid,
                        courseid: courseid
                    }, true);
                    // console.log(link);
                    window.location.href = link;
                    document.getElementById('psgrading-downloader-warning-alert').remove();
                    logElapsedTime();
                } else {
                    setTimeout(checkTaskStatus(taskid, courseid), 5000);
                }


            },
            fail: function (reason) {
                console.log(reason);
            },
        },]);
    }

    function logElapsedTime() {
        const endTime = new Date();
        const elapsedTime = (endTime - startTime) / 1000; // Calculate elapsed time in seconds
        console.log(`Process took ${elapsedTime} seconds.`);
    }

    return {
        init: init
    };
});