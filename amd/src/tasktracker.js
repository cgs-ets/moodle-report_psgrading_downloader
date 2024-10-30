define(['core/ajax', 'core/url', 'core/templates'], function (Ajax, URL, Templates) {

    let startTime;
    function init(taskid, courseid) {
        startTime = new Date();
        checkTaskStatus(taskid, courseid);
    }

    function checkTaskStatus(taskid, courseid) {


        console.log(`Checking task ID ${taskid} course ID ${courseid}`);
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

                    window.location.href = link;
                    document.getElementById('psgrading-downloader-warning-alert').remove();
                    logElapsedTime();
                } else if (response.status === 'failed') {
                    console.log('FAILED');
                    Templates.render('report_psgrading_downloader/alert_danger', {})
                        .done(function (html, js) {
                            Templates.replaceNodeContents(document.getElementById('alert-container'), html, js)
                        })
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