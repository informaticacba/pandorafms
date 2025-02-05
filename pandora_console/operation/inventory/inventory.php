<?php
/**
 * Inventory view.
 *
 * @category   Monitoring.
 * @package    Pandora FMS
 * @subpackage Community
 * @version    1.0.0
 * @license    See below
 *
 *    ______                 ___                    _______ _______ ________
 *   |   __ \.-----.--.--.--|  |.-----.----.-----. |    ___|   |   |     __|
 *  |    __/|  _  |     |  _  ||  _  |   _|  _  | |    ___|       |__     |
 * |___|   |___._|__|__|_____||_____|__| |___._| |___|   |__|_|__|_______|
 *
 * ============================================================================
 * Copyright (c) 2005-2022 Artica Soluciones Tecnologicas
 * Please see http://pandorafms.org for full contribution list
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation for version 2.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * ============================================================================
 */

// Begin.
require_once $config['homedir'].'/include/functions_users.php';
require_once $config['homedir'].'/include/functions_inventory.php';

if (is_ajax() === true) {
    $get_csv_url = (bool) get_parameter('get_csv_url');

    if ($get_csv_url) {
        // $inventory_module = get_parameter ('module_inventory_general_view', 'all');
        $inventory_module = get_parameter('module', 'all');
        $inventory_id_group = (int) get_parameter('id_group', 0);
        // 0 is All groups
        $inventory_search_string = (string) get_parameter('search_string');
        $export = (string) get_parameter('export');
        $utimestamp = (int) get_parameter('utimestamp', 0);
        $inventory_agent = (string) get_parameter('agent', '');
        $order_by_agent = (boolean) get_parameter('order_by_agent', 0);

        // Agent select.
        $agents = [];

        $sql = 'SELECT id_agente, nombre FROM tagente';
        if ($inventory_id_group > 0) {
            $sql .= ' WHERE id_grupo = '.$inventory_id_group;
        } else {
            $user_groups = implode(',', array_keys(users_get_groups($config['id_user'])));

            // Avoid errors if there are no groups.
            if (empty($user_groups) === true) {
                $user_groups = '"0"';
            }

            $sql .= ' WHERE id_grupo IN ('.$user_groups.')';
        }

        $result = db_get_all_rows_sql($sql);
        if ($result !== false) {
            foreach ($result as $row) {
                $agents[$row['id_agente']] = $row['nombre'];
            }
        }

        $agents_select = $agents;

        if (strlen($inventory_agent) == 0) {
            $inventory_id_agent = -1;
            $inventory_agent = __('All');
        } else if ($inventory_agent == __('All')) {
            $inventory_id_agent = 0;
        } else {
            $sql = 'SELECT id_agente
                FROM tagente
                WHERE nombre LIKE "'.$inventory_agent.'"';

            $result = db_get_all_rows_sql($sql);
            $inventory_id_agent = $result[0]['id_agente'];
        }

        // Single agent selected.
        if ($inventory_id_agent > 0 && isset($agents[$inventory_id_agent]) === true) {
            $agents = [$inventory_id_agent => $agents[$inventory_id_agent]];
        }

        $agents_ids = array_keys($agents);
        if (count($agents_ids) > 0) {
            $inventory_data = inventory_get_data(
                $agents_ids,
                $inventory_module,
                $utimestamp,
                $inventory_search_string,
                $export,
                false,
                $order_by_agent
            );

            if ((int) $inventory_data === ERR_NODATA) {
                $inventory_data = '';
            }
        }

        return;
    }

    return;
}

global $config;

check_login();


$is_metaconsole = is_metaconsole();

if ($is_metaconsole === true) {
    open_meta_frame();
}

if (! check_acl($config['id_user'], 0, 'AR') && ! check_acl($config['id_user'], 0, 'AW')) {
    db_pandora_audit(
        AUDIT_LOG_ACL_VIOLATION,
        'Trying to access Inventory'
    );
    include 'general/noaccess.php';
    return;
}

require_once $config['homedir'].'/include/functions_users.php';
require_once $config['homedir'].'/include/functions_inventory.php';

// Header.
ui_print_standard_header(
    __('Inventory'),
    'images/op_inventory.png',
    false,
    '',
    false,
    [],
    [
        [
            'link'  => '',
            'label' => __('Monitoring'),
        ],
    ]
);

$inventory_id_agent = (int) get_parameter('agent_id', -1);
$inventory_agent = (string) get_parameter('agent', '');
if (strlen($inventory_agent) == 0) {
    $inventory_id_agent = -1;
    $inventory_agent = __('All');
} else if ($inventory_agent == __('All')) {
    $inventory_id_agent = 0;
}

$inventory_module = get_parameter('module_inventory_general_view');
$inventory_id_group = (int) get_parameter('id_group');
$inventory_search_string = (string) get_parameter('search_string');
$order_by_agent = (bool) get_parameter('order_by_agent');
$export = (string) get_parameter('export');
$utimestamp = (int) get_parameter('utimestamp');
$submit_filter = (bool) get_parameter('submit_filter');

$pagination_url_parameters = [
    'inventory_id_agent' => $inventory_id_agent,
    'inventory_agent'    => $inventory_agent,
    'inventory_id_group' => $inventory_id_group,
];

$noFilterSelected = false;
// Get variables.
if ($is_metaconsole === true) {
    $nodes_connection = metaconsole_get_connections();
    $id_server = (int) get_parameter('id_server', 0);
    $pagination_url_parameters['id_server'] = $id_server;

    if ($inventory_id_agent > 0) {
        $inventory_id_server = (int) get_parameter('id_server_agent', -1);
        $pagination_url_parameters['inventory_id_server'] = $inventory_id_server;

        if ($inventory_id_server !== -1) {
            $id_server = $inventory_id_server;
            $pagination_url_parameters['id_server'] = $id_server;
        }
    }

    // No filter selected.
    $noFilterSelected = $inventory_id_agent === -1 && $inventory_id_group === 0 && $id_server === 0;
}

if ($is_metaconsole === true) {
    if ($id_server > 0) {
        $connection = metaconsole_get_connection_by_id($id_server);
        $agents_node = metaconsole_get_agents_servers($connection['server_name'], $inventory_id_group);
        $node = metaconsole_get_servers($id_server);

        if (metaconsole_connect($connection) !== NOERR) {
            ui_print_error_message(
                __('There was a problem connecting with the node')
            );
        }

        $sql = 'SELECT DISTINCT name as indexname, name
            FROM tmodule_inventory, tagent_module_inventory
            WHERE tmodule_inventory.id_module_inventory = tagent_module_inventory.id_module_inventory';
        if ($inventory_id_agent > 0) {
            $sql .= ' AND id_agente = '.$inventory_id_agent;
        }

        $result_module = db_get_all_rows_sql($sql);
        if ($submit_filter === true) {
            $inventory_data .= inventory_get_data(
                array_keys($agents_node),
                $inventory_module,
                $utimestamp,
                $inventory_search_string,
                $export,
                false,
                $order_by_agent,
                $node,
                $pagination_url_parameters
            );
        }

        // Restore db connection.
        metaconsole_restore_db();
    } else {
        $result_module = [];
        foreach ($nodes_connection as $key => $server) {
            $agents_node = metaconsole_get_agents_servers($server['server_name'], $inventory_id_group);
            $connection = metaconsole_get_connection($server['server_name']);
            if (metaconsole_connect($connection) !== NOERR) {
                continue;
            }

            $sql = 'SELECT DISTINCT name as indexname, name
                FROM tmodule_inventory, tagent_module_inventory
                WHERE tmodule_inventory.id_module_inventory = tagent_module_inventory.id_module_inventory';
            if ($inventory_id_agent > 0) {
                $sql .= ' AND id_agente = '.$inventory_id_agent;
            }

            $result = db_get_all_rows_sql($sql);

            if ($result !== false) {
                $result_module = array_merge($result_module, $result);
                if ($submit_filter === true) {
                    // Get the data.
                    $result_data = inventory_get_data(
                        array_keys($agents_node),
                        $inventory_module,
                        $utimestamp,
                        $inventory_search_string,
                        $export,
                        false,
                        $order_by_agent,
                        $server,
                        $pagination_url_parameters
                    );
                    if ($result_data !== ERR_NODATA) {
                        $inventory_data .= $result_data;
                    }
                }
            }

            // Restore db connection.
            metaconsole_restore_db();
        }
    }

    $fields = [];
    foreach ($result_module as $row) {
        $id = array_shift($row);
        $value = array_shift($row);
        $fields[$id] = $value;
    }
}

$agent_a = (bool) check_acl($config['id_user'], 0, 'AR');
$agent_w = (bool) check_acl($config['id_user'], 0, 'AW');
$access = ($agent_a === true) ? 'AR' : (($agent_w === true) ? 'AW' : 'AR');

if (is_metaconsole() === true) {
    $filteringFunction = 'active_inventory_submit()';
    ui_print_info_message(['no_close' => true, 'message' => __('You must select at least one filter.'), 'force_class' => 'select_one_filter']);
    ?>
    <script type="text/javascript">
        function active_inventory_submit() {
            if (
                $("#id_group").val() == 0 &&
                $("#id_server").val() == 0 &&
                $("#module_inventory_general_view").val() == 0 &&
                $("#text-search_string").val() === ''
            ) {
                $("#submit-submit_filter").attr("disabled", true);
                $(".select_one_filter").css("display", "table");
            } else {
                $("#submit-submit_filter").attr("disabled", false);
                $(".select_one_filter").css("display", "none");
            }
        }
    </script>
    <?php
} else {
    $filteringFunction = '';
}

echo '<form method="POST" action="index.php?sec=estado&sec2=operation/inventory/inventory" name="form_inventory">';

$table = new stdClass();
$table->width = '100%';
$table->class = 'databox filters';
$table->size = [];
$table->size[0] = '120px';
$table->cellpadding = 0;
$table->cellspacing = 0;
$table->data = [];
$table->rowspan[0][4] = 2;

if ($is_metaconsole === true) {
    // Node select.
    $nodes = [];
    foreach ($nodes_connection as $row) {
        $nodes[$row['id']] = $row['server_name'];
    }

    $table->data[-1][0] = '<strong>'.__('Server').'</strong>';
    $table->data[-1][1] = html_print_select($nodes, 'id_server', $id_server, $filteringFunction, __('All'), 0, true, false, true, '', false, 'min-width: 250px; max-width: 300px;');
}

// Group select.
$table->data[0][0] = '<strong>'.__('Group').'</strong>';

$table->data[0][1] = '<div class="w250px">';
$table->data[0][1] .= html_print_select_groups(
    $config['id_user'],
    $access,
    true,
    'id_group',
    $inventory_id_group,
    $filteringFunction,
    '',
    '1',
    true,
    false,
    true,
    '',
    false
);
$table->data[0][1] .= '</div>';

// Module selected.
$table->data[0][2] = '<strong>'.__('Module').'</strong>';

if ($is_metaconsole === true) {
    $table->data[0][3] = html_print_select($fields, 'module_inventory_general_view', $inventory_module, $filteringFunction, __('All'), 0, true, false, true, '', false, 'min-width: 194px; max-width: 200px;');
} else {
    $sql = 'SELECT name as indexname, name
	FROM tmodule_inventory, tagent_module_inventory
	WHERE tmodule_inventory.id_module_inventory = tagent_module_inventory.id_module_inventory';
    if ($inventory_id_agent > 0) {
        $sql .= ' AND id_agente = '.$inventory_id_agent;
    }

    $table->data[0][3] = html_print_select_from_sql($sql, 'module_inventory_general_view', $inventory_module, '', __('All'), 'all', true, false, false);
}


// Button of submit.
$table->data[0][4] = html_print_submit_button(__('Search'), 'submit_filter', $noFilterSelected, "class='sub search'", true);

// Agent select.
if ($is_metaconsole === false) {
    $agents = [];
    $sql = 'SELECT id_agente, nombre FROM tagente';
    if ($inventory_id_group > 0) {
        $sql .= ' WHERE id_grupo = '.$inventory_id_group;
    } else {
        $user_groups = implode(',', array_keys(users_get_groups($config['id_user'])));

        // Avoid errors if there are no groups.
        if (empty($user_groups) === true) {
            $user_groups = '"0"';
        }

        $sql .= ' WHERE id_grupo IN ('.$user_groups.')';
    }

    $result = db_get_all_rows_sql($sql);
    if ($result) {
        foreach ($result as $row) {
            $agents[$row['id_agente']] = $row['nombre'];
        }
    }
}

$table->data[1][0] = '<strong>'.__('Agent').'</strong>';

$params = [];
$params['return'] = true;
$params['show_helptip'] = true;
$params['input_name'] = 'agent';
$params['value'] = $inventory_agent;
$params['selectbox_id'] = 'module_inventory_general_view';
$params['javascript_is_function_select'] = true;
$params['javascript_function_action_after_select'] = 'this.form.submit';
$params['use_hidden_input_idagent'] = true;
$params['print_hidden_input_idagent'] = true;
$params['hidden_input_idagent_id'] = 'hidden-autocomplete_id_agent';
$params['hidden_input_idagent_name'] = 'agent_id';
$params['hidden_input_idagent_value'] = $inventory_id_agent;
if ($is_metaconsole === true) {
    $params['print_input_id_server'] = true;
    $params['input_id_server_id'] = 'hidden-autocomplete_id_server';
    $params['input_id_server_name'] = 'id_server_agent';
    $params['input_id_server_value'] = $inventory_id_server;
    $params['metaconsole_enabled'] = true;
}

$table->data[1][1] = ui_print_agent_autocomplete_input($params);

// String search_string.
$table->data[1][2] = '<strong>'.__('Search').'</strong>';
$table->data[1][3] = html_print_input_text('search_string', $inventory_search_string, '', 25, 0, true, false, false, '', '', $filteringFunction, 'off', false, $filteringFunction);

// Date filter. In Metaconsole has not reason for show.
if (is_metaconsole() === false) {
    $table->data[2][0] = '<strong>'.__('Date').'</strong>';
    $dates = inventory_get_dates($inventory_module, $inventory_agent, $inventory_id_group);
    $table->data[2][1] = html_print_select($dates, 'utimestamp', $utimestamp, '', __('Last'), 0, true);
}

// Order by agent filter.
$table->data[2][2] = '<strong>'.__('Order by agent').'</strong>';

$table->data[2][3] = html_print_checkbox('order_by_agent', 1, $order_by_agent, true, false, '');

html_print_table($table);

echo '</form>';

// No agent selected or no search performed.
if ($inventory_id_agent < 0 || $submit_filter === false) {
    echo '&nbsp;</td></tr><tr><td>';

    return;
}

if ($is_metaconsole === false) {
    // Single agent selected.
    if ($inventory_id_agent > 0 && isset($agents[$inventory_id_agent]) === true) {
        $agents = [$inventory_id_agent => $agents[$inventory_id_agent]];
    }

    $agents_ids = array_keys($agents);
    if (count($agents_ids) > 0) {
        $inventory_data = inventory_get_data(
            $agents_ids,
            $inventory_module,
            $utimestamp,
            $inventory_search_string,
            $export,
            false,
            $order_by_agent,
            '',
            $pagination_url_parameters
        );
    }

    if (count($agents_ids) === 0 || (int) $inventory_data === ERR_NODATA) {
        ui_print_info_message(['no_close' => true, 'message' => __('No data found.') ]);
        echo '&nbsp;</td></tr><tr><td>';

        return;
    }

    echo "<div id='url_csv' style='width: ".$table->width.";' class='inventory_table_buttons'>";
    echo "<a href='javascript: get_csv_url(\"".$inventory_module.'",'.$inventory_id_group.','.'"'.$inventory_search_string.'",'.$utimestamp.','.'"'.$inventory_agent.'",'.$order_by_agent.")'><span>".__('Export this list to CSV').'</span>'.html_print_image('images/csv.png', true, ['title' => __('Export this list to CSV')]).'</a>';
    echo '</div>';
    echo "<div id='loading_url' style='display: none; width: ".$table->width."; text-align: right;'>".html_print_image('images/spinner.gif', true).'</div>';
    ?>
    <script type="text/javascript">
        function get_csv_url(module, id_group, search_string, utimestamp, agent, order_by_agent) {
            $("#url_csv").hide();
            $("#loading_url").show();
            $.ajax ({
                            method:'GET',
                            url:'ajax.php',
                            datatype:'html',
                            data:{
                                    "page" : "operation/inventory/inventory",
                                    "get_csv_url" : 1,
                                    "module" : module,
                                    "id_group" : id_group,
                                    "search_string" : search_string,
                                    "utimestamp" : utimestamp,
                                    "agent" : agent,
                                    "export": true,
                                    "order_by_agent": order_by_agent
                            },
                            success: function (data, status) {
                                    $("#url_csv").html(data);
                                    $("#loading_url").hide();
                                    $("#url_csv").show();
                            }
                    });

        }
    </script>
    <?php
    echo $inventory_data;
} else {
    if (empty($inventory_data) === true) {
        ui_print_info_message(['no_close' => true, 'message' => __('No data found.') ]);
    } else {
        echo $inventory_data;
    }

    close_meta_frame();
}

ui_require_jquery_file('pandora.controls');
ui_require_jquery_file('ajaxqueue');
ui_require_jquery_file('bgiframe');
?>

<script type="text/javascript">
/* <![CDATA[ */
    $(document).ready (function () {
        <?php if (is_metaconsole() === true) : ?>
        active_inventory_submit();
        <?php endif; ?>
        $("#id_group").click (
            function () {
                $(this).css ("width", "auto");
            }
        );

        $("#id_group").blur (function () {
            $(this).css ("width", "180px");
        });
    });
/* ]]> */
</script>
