$(function(){
    var menu = {
        update: function(){
            $.get('index.php', {'action': 'index', 'method': 'menuUpdate'}, function (data) {
                $('.menu').html(data);
            });
        }
    };

    var tasks_data = {
        current_status: 0,
        current_offset: 0,
        current_uniqid: 0,
        need_list_update: true,
        need_details_update: false,
        list_container_html: "#tasks_list",
        list_element_html: '.task-list-element',
        details_html: "#task-details",
        menu_html: "#menu",
        list_load: function(offset) {
            offset = typeof(offset) == "undefined" ? 0 : offset;

            var self = this;

            this.need_list_update = offset == 0;
            this.current_offset = offset;

            $.get('index.php', {action: 'index', method: 'tasksList', status: this.current_status, offset : offset}, function (data) {
                $(self.list_container_html).html(data);
            });
        },
        detail_load: function(uniqid){
            var self = this;
            uniqid = typeof(uniqid) == "undefined" ? this.current_uniqid : uniqid;
            this.current_uniqid = uniqid;
            $.get('index.php', {'action' : 'index', 'method' : 'taskDetails', 'id' : uniqid}, function (data) {
                $(self.details_html).html(data);

                $("#task-header").before($("#task-action-template").html());
            });
        },
        action_cancel_task: function(){
            $.ajax({
                url: "index.php",
                data: { 'action': 'index', 'method': 'taskAction', 'uniqid': this.current_uniqid, 'task_action' : 'cancel' },
                dataType: "json",
                success: function (data) {
                    console.log(data);
                    if(data.hasOwnProperty('error'))
                    {
                        alert(data['error']);
                        return;
                    }

                    menu.update();
                    tasks_data.list_load(tasks_data.current_offset);
                    tasks_data.detail_load();
                }
            });
        },
        action_auto_update: function(){
            this.need_details_update = true;
        }
    };

    tasks_data.list_load();

    $(document).on('click', tasks_data.list_element_html,  function (e) {
        tasks_data.detail_load($(this).data('uniqid'));
        tasks_data.need_details_update = false;
    });

    $('.list-update').on('click', function(e){
        e.preventDefault();
        tasks_data.current_status = $(this).data('status');
        tasks_data.list_load();
    });

    $(document).on('click', '.page', function (e) {
        tasks_data.list_load($(this).data('offset'));
        e.preventDefault();
    });

    $(document).on('click', '.task-action', function(e){
        e.preventDefault();
        var action_name = "action_" + $(this).data("action");
        if(!tasks_data.hasOwnProperty(action_name)) return false;

        tasks_data[action_name]();

        return true;
    });

    setInterval(function () {
        menu.update();

        if(tasks_data.need_details_update){
            tasks_data.detail_load();
        }

        if(tasks_data.need_list_update) {
            tasks_data.list_load();
        }
    }, 10000);
});
