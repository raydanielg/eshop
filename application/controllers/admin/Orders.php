<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Orders extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('Order_model');

        if (!has_permissions('read', 'orders')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        } else {
            $this->session->set_flashdata('authorize_flag', "");
        }
    }

    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'manage-orders';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Order Management | ' . $settings['app_name'];
            $this->data['meta_description'] = ' Order Management  | ' . $settings['app_name'];
            $this->data['about_us'] = get_settings('about_us');
            $this->data['curreny'] = get_settings('currency');
            $orders_count['awaiting'] = orders_count("awaiting");
            $orders_count['received'] = orders_count("received");
            $orders_count['processed'] = orders_count("processed");
            $orders_count['shipped'] = orders_count("shipped");
            $orders_count['delivered'] = orders_count("delivered");
            $orders_count['cancelled'] = orders_count("cancelled");
            $orders_count['returned'] = orders_count("returned");
            $this->data['status_counts'] = $orders_count;
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function view_orders()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->Order_model->get_orders_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }
    public function view_order_items()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->Order_model->get_order_items_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function delete_orders()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('delete', 'orders'), PERMISSION_ERROR_MSG, 'orders')) {
                return false;
            }
            $delete = array(
                "order_items" => 0,
                "orders" => 0,
                "order_bank_transfer" => 0
            );
            $orders = $this->db->where(' oi.order_id=' . $_GET['id'])->join('orders o', 'o.id=oi.order_id', 'right')->get('order_items oi')->result_array();
            if (!empty($orders)) {
                // delete orders
                if (delete_details(['order_id' => $_GET['id']], 'order_items')) {
                    $delete['order_items'] = 1;
                }
                if (delete_details(['id' => $_GET['id']], 'orders')) {
                    $delete['orders'] = 1;
                }
                if (delete_details(['order_id' => $_GET['id']], 'order_bank_transfer')) {
                    $delete['order_bank_transfer'] = 1;
                }
            }
            $deleted = FALSE;
            if (!in_array(0, $delete)) {
                $deleted = TRUE;
            }
            if ($deleted == TRUE) {
                $response['error'] = false;
                $response['message'] = 'Deleted Successfully';
                $response['permission'] = !has_permissions('delete', 'orders');
            } else {
                $response['error'] = true;
                $response['message'] = 'Something went wrong';
            }
            echo json_encode($response);
        } else {
            redirect('admin/login', 'refresh');
        }
    }
    public function delete_order_items()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('delete', 'orders'), PERMISSION_ERROR_MSG, 'orders')) {
                return false;
            }
            $delete = array(
                "order_items" => 0,
                "orders" => 0,
                "order_bank_transfer" => 0
            );
            /* check order items */
            $order_items = fetch_details('order_items', ['id' => $_GET['id']], 'id,order_id');
            if (delete_details(['id' => $_GET['id']], 'order_items')) {
                $delete['order_items'] = 1;
            }
            $res_order_id = array_values(array_unique(array_column($order_items, "order_id")));
            for ($i = 0; $i < count($res_order_id); $i++) {
                $orders = $this->db->where(' oi.order_id=' . $res_order_id[$i])->join('orders o', 'o.id=oi.order_id', 'right')->get('order_items oi')->result_array();
                if (empty($orders)) {
                    // delete orders
                    if (delete_details(['id' => $res_order_id[$i]], 'orders')) {
                        $delete['orders'] = 1;
                    }
                    if (delete_details(['order_id' => $res_order_id[$i]], 'order_bank_transfer')) {
                        $delete['order_bank_transfer'] = 1;
                    }
                }
            }

            if ($delete['order_items'] == TRUE) {
                $response['error'] = false;
                $response['message'] = 'Deleted Successfully';
                $response['permission'] = !has_permissions('delete', 'orders');
            } else {
                $response['error'] = true;
                $response['message'] = 'Something went wrong';
            }
            echo json_encode($response);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    /* Update complete order status */
    public function update_orders()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'orders'), PERMISSION_ERROR_MSG, 'orders')) {
                return false;
            }
            $msg = '';
            $order_method = fetch_details('orders', ['id' => $_POST['orderid']], 'payment_method');
            if ($order_method[0]['payment_method'] == 'bank_transfer') {
                $bank_receipt = fetch_details('order_bank_transfer', ['order_id' => $_POST['orderid']]);
                $transaction_status = fetch_details('transactions', ['order_id' => $_POST['orderid']], 'status');
                if (empty($bank_receipt) || strtolower($transaction_status[0]['status']) != 'success') {
                    $this->response['error'] = true;
                    $this->response['message'] = "Order Status can not update, Bank verification is remain from transactions.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
            }
            if (isset($_POST['deliver_by']) && !empty($_POST['deliver_by']) && isset($_POST['orderid']) && !empty($_POST['orderid'])) {
                $where = "id = " . $_POST['orderid'] . "";
                $current_delivery_boy = fetch_details('orders', $where, 'delivery_boy_id');
                $settings = get_settings('system_settings', true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $user_res = fetch_details('users', ['id' => $_POST['deliver_by']], 'fcm_id,username');
                $fcm_ids = array();
                if (isset($user_res[0]) && !empty($user_res[0])) {
                    if (isset($current_delivery_boy[0]['delivery_boy_id']) && $current_delivery_boy[0]['delivery_boy_id'] == $_POST['deliver_by']) {
                        $fcmMsg = array(
                            'title' => "Order status updated",
                            'body' => 'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['val'] . ' for order ID #' . $_POST['orderid'] . ' assigned to you please take note of it! Thank you. Regards ' . $app_name . '',
                            'type' => "order"
                        );
                    } else {
                        $fcmMsg = array(
                            'title' => "You have new order to deliver",
                            'body' => 'Hello Dear ' . $user_res[0]['username'] . ' you have new order to be deliver order ID #' . $_POST['orderid'] . ' please take note of it! Thank you. Regards ' . $app_name . '',
                            'type' => "order"
                        );
                        $msg = 'Delivery Boy Updated. ';
                    }
                }
                if (!empty($user_res[0]['fcm_id'])) {
                    $fcm_ids[0][] = $user_res[0]['fcm_id'];
                    send_notification($fcmMsg, $fcm_ids);
                }

                $where = [
                    'id' => $_POST['orderid']
                ];


                if ($this->Order_model->update_order(['delivery_boy_id' => $_POST['deliver_by']], $where)) {
                    $delivery_error = false;
                }
            }

            $res = validate_order_status($_POST['orderid'], $_POST['val'], 'orders');
            if ($res['error']) {
                $this->response['error'] = true;
                $this->response['message'] = $msg . $res['message'];
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }

            $priority_status = [
                'received' => 0,
                'processed' => 1,
                'shipped' => 2,
                'delivered' => 3,
                'cancelled' => 4,
                'returned' => 5,
            ];

            $update_status = 1;
            $error = TRUE;
            $message = '';

            $where_id = "id = " . $_POST['orderid'] . " and (active_status != 'cancelled' and active_status != 'returned' ) ";
            $where_order_id = "order_id = " . $_POST['orderid'] . " and (active_status != 'cancelled' and active_status != 'returned' ) ";

            $order_items_details = fetch_details('order_items', $where_order_id, 'active_status');
            $counter = count($order_items_details);
            $cancel_counter = 0;
            foreach ($order_items_details as $row) {
                if ($row['active_status'] == 'cancelled') {
                    ++$cancel_counter;
                }
            }
            if ($cancel_counter == $counter) {
                $update_status = 0;
            }

            if (isset($_POST['orderid']) && isset($_POST['field']) && isset($_POST['val'])) {
                if ($_POST['field'] == 'status' && $update_status == 1) {

                    $current_orders_status = fetch_details('orders', $where_id, 'user_id,active_status');
                    $user_id = $current_orders_status[0]['user_id'];
                    $current_orders_status = $current_orders_status[0]['active_status'];

                    if ($priority_status[$_POST['val']] > $priority_status[$current_orders_status]) {
                        $set = [
                            $_POST['field'] => $_POST['val'] // status => 'proceesed'
                        ];

                        // Update Active Status of Order Table										
                        if ($this->Order_model->update_order($set, $where_id, $_POST['json'])) {
                            if ($this->Order_model->update_order(['active_status' => $_POST['val']], $where_id)) {
                                if ($this->Order_model->update_order($set, $where_order_id, $_POST['json'], 'order_items')) {
                                    if ($this->Order_model->update_order(['active_status' => $_POST['val']], $where_order_id, false, 'order_items')) {
                                        $error = false;
                                    }
                                }
                            }
                        }

                        if ($error == false) {
                            /* Send notification */
                            $settings = get_settings('system_settings', true);
                            $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                            $user_res = fetch_details('users', ['id' => $user_id], 'username,fcm_id');
                            $fcm_ids = array();
                            if (!empty($user_res[0]['fcm_id'])) {
                                $fcmMsg = array(
                                    'title' => "Order status updated",
                                    'body' => 'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['val'] . ' for your order ID #' . $_POST['orderid'] . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '',
                                    'type' => "order"
                                );

                                $fcm_ids[0][] = $user_res[0]['fcm_id'];
                                send_notification($fcmMsg, $fcm_ids);
                            }
                            /* Process refer and earn bonus */
                            process_refund($_POST['orderid'], $_POST['val'], 'orders');
                            if (trim($_POST['val'] == 'cancelled')) {
                                $data = fetch_details('order_items', ['order_id' => $_POST['orderid']], 'product_variant_id,quantity');
                                $product_variant_ids = [];
                                $qtns = [];
                                foreach ($data as $d) {
                                    array_push($product_variant_ids, $d['product_variant_id']);
                                    array_push($qtns, $d['quantity']);
                                }

                                update_stock($product_variant_ids, $qtns, 'plus');
                            }
                            $response = process_referral_bonus($user_id, $_POST['orderid'], $_POST['val']);
                            $message = 'Status Updated Successfully';
                        }
                    }
                }
                if ($error == true) {
                    $message = $msg . ' Status Updation Failed';
                }
            }
            $response['error'] = $error;
            $response['message'] = $message;
            $response['total_amount'] = (!empty($data) ? $data : '');
            $response['csrfName'] = $this->security->get_csrf_token_name();
            $response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function edit_orders()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (!has_permissions('read', 'orders')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }
            $bank_transfer = array();
            $this->data['main_page'] = FORMS . 'edit-orders';
            $settings = get_settings('system_settings', true);

            $this->data['title'] = 'View Order | ' . $settings['app_name'];
            $this->data['meta_description'] = 'View Order | ' . $settings['app_name'];
            $res = $this->Order_model->get_order_details(['o.id' => $_GET['edit_id']]);
            if (is_exist(['id' => $res[0]['address_id']], 'addresses')) {
                $area_id = fetch_details('addresses', ['id' => $res[0]['address_id']], 'area_id');
                if (!empty($area_id)) {
                    $zipcode_id = fetch_details('areas', ['id' => $area_id[0]['area_id']], 'zipcode_id');
                    $this->data['delivery_res'] = $this->db->where(['ug.group_id' => '3', 'u.active' => 1])->where('find_in_set(' . $zipcode_id[0]['zipcode_id'] . ', u.serviceable_zipcodes)!=', 0)->join('users_groups ug', 'ug.user_id = u.id')->get('users u')->result_array();
                }
            } else {
                $this->data['delivery_res'] = $this->db->where(['ug.group_id' => '3', 'u.active' => 1])->join('users_groups ug', 'ug.user_id = u.id')->get('users u')->result_array();
            }
            if ($res[0]['payment_method'] == "bank_transfer") {
                $bank_transfer = fetch_details('order_bank_transfer', ['order_id' => $res[0]['order_id']]);
            }
            if (isset($_GET['edit_id']) && !empty($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
                $items = [];
                foreach ($res as $row) {
                    $temp['id'] = $row['order_item_id'];
                    $temp['item_otp'] = $row['item_otp'];
                    $temp['tracking_id'] = $row['tracking_id'];
                    $temp['courier_agency'] = $row['courier_agency'];
                    $temp['url'] = $row['url'];
                    $temp['product_id'] = $row['product_id'];
                    $temp['product_variant_id'] = $row['product_variant_id'];
                    $temp['product_type'] = $row['type'];
                    $temp['pname'] = $row['pname'];
                    $temp['quantity'] = $row['quantity'];
                    $temp['is_cancelable'] = $row['is_cancelable'];
                    $temp['is_returnable'] = $row['is_returnable'];
                    $temp['tax_amount'] = $row['tax_amount'];
                    $temp['discounted_price'] = $row['discounted_price'];
                    $temp['price'] = $row['price'];
                    $temp['row_price'] = $row['row_price'];
                    $temp['active_status'] = $row['oi_active_status'];
                    $temp['product_image'] = $row['product_image'];
                    $temp['product_variants'] = get_variants_values_by_id($row['product_variant_id']);
                    array_push($items, $temp);
                }
                $this->data['order_detls'] = $res;
                $this->data['bank_transfer'] = $bank_transfer;
                $this->data['items'] = $items;
                $this->data['settings'] = get_settings('system_settings', true);
                $this->load->view('admin/template', $this->data);
            } else {
                redirect('admin/orders/', 'refresh');
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    /* Update individual order item status */
    public function update_order_status()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'orders'), PERMISSION_ERROR_MSG, 'orders')) {
                return false;
            }

            $this->form_validation->set_rules('order_item_id[]', 'Order Item ID', 'trim|required|xss_clean');
            $this->form_validation->set_rules('deliver_by', 'Delvery Boy Id', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('status', 'Status', 'trim|xss_clean|in_list[received,processed,shipped,delivered,cancelled,returned]');

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = strip_tags(validation_errors());
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
            $order_itam_ids = $_POST['order_item_id'];
            $order_items = fetch_details('order_items', "", '*', "", "", "", "", "id", $order_itam_ids);
            if (empty($order_items)) {
                $this->response['error'] = true;
                $this->response['message'] = 'No Order Item Found';
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }

            if (count($order_itam_ids) != count($order_items)) {
                $this->response['error'] = true;
                $this->response['message'] = 'Some item was not found on status update';
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
            // delivery boy update here
            for ($j = 0; $j < count($order_items); $j++) {
                $order_item_id = $order_items[$j]['id'];
                /* velidate bank transfer method status */
                $order_method = fetch_details('orders', ['id' => $order_items[$j]['order_id']], 'payment_method');
                if ($order_method[0]['payment_method'] == 'bank_transfer') {
                    $bank_receipt = fetch_details('order_bank_transfer', ['order_id' => $order_items[$j]['order_id']]);
                    $transaction_status = fetch_details('transactions', ['order_id' => $order_items[$j]['order_id']], 'status');
                    if ($_POST['status'] != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[0]['status']) != 'success' || $bank_receipt[0]['status'] == "0" || $bank_receipt[0]['status'] == "1")) {
                        $this->response['error'] = true;
                        $this->response['message'] = "Order item stat nus cann't update, Bank verification is remain from transactions for this order.";
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['data'] = array();
                        print_r(json_encode($this->response));
                        return false;
                    }
                }
            }
            $message = '';
            $delivery_boy_updated = 0;
            $delivery_boy_id = (isset($_POST['deliver_by']) && !empty(trim($_POST['deliver_by']))) ? $this->input->post('deliver_by', true) : 0;
            if (!empty($delivery_boy_id)) {
                $delivery_boy = fetch_details('users', ['id' => trim($delivery_boy_id)], 'id');
                if (empty($delivery_boy)) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Invalid Delivery Boy";
                    $this->response['data'] = array();
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $current_delivery_boys = fetch_details('order_items', "",  'delivery_boy_id', "", "", "", "", "id", $order_itam_ids);
                    $settings = get_settings('system_settings', true);
                    $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                    if (isset($current_delivery_boys[0]['delivery_boy_id']) && !empty($current_delivery_boys[0]['delivery_boy_id'])) {
                        $user_res = fetch_details('users', "",  'fcm_id,username', "", "", "", "", "id", array_column($current_delivery_boys, "delivery_boy_id"));
                    } else {
                        $user_res = fetch_details('users', ['id' => $delivery_boy_id], 'fcm_id,username');
                    }

                    $fcm_ids = array();
                    if (isset($user_res[0]) && !empty($user_res[0])) {
                        $current_delivery_boy = array_column($current_delivery_boys, "delivery_boy_id");
                        if (!empty($current_delivery_boy[0]) && count($current_delivery_boy) > 1) {
                            for ($i = 0; $i < count($current_delivery_boys); $i++) {
                                $fcmMsg = array(
                                    'title' => "Order status updated",
                                    'body' => 'Hello Dear ' . $user_res[$i]['username'] . ' order status updated to ' . $_POST['status'] . ' for order ID #' . $order_items[0]['order_id'] . ' assigned to you please take note of it! Thank you. Regards ' . $app_name . '',
                                    'type' => "order"
                                );
                                if (!empty($user_res[$i]['fcm_id'])) {
                                    $fcm_ids[0][] = $user_res[$i]['fcm_id'];
                                }
                            }
                            $message = 'Delivery Boy Updated.';
                            $delivery_boy_updated = 1;
                        } else {
                            if (isset($current_delivery_boys[0]['delivery_boy_id']) && $current_delivery_boys[0]['delivery_boy_id'] == $_POST['deliver_by']) {
                                $fcmMsg = array(
                                    'title' => "Order status updated.",
                                    'body' => 'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['status'] . ' for order ID #' . $order_items[0]['order_id'] . ' assigned to you please take note of it! Thank you. Regards ' . $app_name . '',
                                    'type' => "order"
                                );
                                $message = 'Delivery Boy Updated';
                                $delivery_boy_updated = 1;
                            } else {
                                $fcmMsg = array(
                                    'title' => "You have new order to deliver",
                                    'body' => 'Hello Dear ' . $user_res[0]['username'] . ' you have new order to be deliver order ID #' . $order_items[0]['order_id'] . ' please take note of it! Thank you. Regards ' . $app_name . '',
                                    'type' => "order"
                                );
                                $message = 'Delivery Boy Updated.';
                                $delivery_boy_updated = 1;
                            }
                            if (!empty($user_res[0]['fcm_id'])) {
                                $fcm_ids[0][] = $user_res[0]['fcm_id'];
                            }
                        }
                    }
                    if (!empty($fcm_ids)) {
                        send_notification($fcmMsg, $fcm_ids);
                    }
                    if ($this->Order_model->update_order(['delivery_boy_id' => $delivery_boy_id], $order_itam_ids, false, 'order_items')) {
                        $delivery_error = false;
                    }
                }
            }

            $item_ids = implode(",", $_POST['order_item_id']);
            $res = validate_order_status($item_ids, $_POST['status']);

            if ($res['error']) {
                $this->response['error'] = $delivery_boy_updated == 1 ? false : true;
                $this->response['message'] = (isset($_POST['status']) && !empty($_POST['status']) && $delivery_error == FALSE) ? $message . $res['message'] :  $message;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }

            if (!empty($order_items)) {
                for ($j = 0; $j < count($order_items); $j++) {
                    $order_item_id = $order_items[$j]['id'];
                    /* velidate bank transfer method status */
                    $order_method = fetch_details('orders', ['id' => $order_items[$j]['order_id']], 'payment_method');
                    if ($order_method[0]['payment_method'] == 'bank_transfer') {
                        $bank_receipt = fetch_details('order_bank_transfer', ['order_id' => $order_items[$j]['order_id']]);
                        $transaction_status = fetch_details('transactions', ['order_id' => $order_items[$j]['order_id']], 'status');
                        if ($_POST['status'] != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[$j]['status']) != 'success' || $bank_receipt[0]['status'] == "0" || $bank_receipt[0]['status'] == "1")) {
                            $this->response['error'] = true;
                            $this->response['message'] = "Order item status can not update, Bank verification is remain from transactions for this order.";
                            $this->response['csrfName'] = $this->security->get_csrf_token_name();
                            $this->response['csrfHash'] = $this->security->get_csrf_hash();
                            $this->response['data'] = array();
                            print_r(json_encode($this->response));
                            return false;
                        }
                    }

                    // processing order items
                    $order_item_res = $this->db->select(' * , (Select count(id) from order_items where order_id = oi.order_id ) as order_counter ,(Select count(active_status) from order_items where active_status ="cancelled" and order_id = oi.order_id ) as order_cancel_counter , (Select count(active_status) from order_items where active_status ="returned" and order_id = oi.order_id ) as order_return_counter,(Select count(active_status) from order_items where active_status ="delivered" and order_id = oi.order_id ) as order_delivered_counter , (Select count(active_status) from order_items where active_status ="processed" and order_id = oi.order_id ) as order_processed_counter , (Select count(active_status) from order_items where active_status ="shipped" and order_id = oi.order_id ) as order_shipped_counter , (Select status from orders where id = oi.order_id ) as order_status ')
                        ->where(['id' => $order_item_id])
                        ->get('order_items oi')->result_array();

                    if ($this->Order_model->update_order(['status' => $_POST['status']], ['id' => $order_item_res[0]['id']], true, 'order_items')) {
                        $this->Order_model->update_order(['active_status' => $_POST['status']], ['id' => $order_item_res[0]['id']], false, 'order_items');
                        process_refund($order_item_res[0]['id'], $_POST['status'], 'order_items');
                        if (trim($_POST['status']) == 'cancelled' || trim($_POST['status']) == 'returned') {
                            $data = fetch_details('order_items', ['id' => $order_item_id], 'product_variant_id,quantity');
                            update_stock($data[0]['product_variant_id'], $data[0]['quantity'], 'plus');
                        }
                        if (($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_cancel_counter']) + 1 && $_POST['status'] == 'cancelled') ||  ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_return_counter']) + 1 && $_POST['status'] == 'returned') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_delivered_counter']) + 1 && $_POST['status'] == 'delivered') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_processed_counter']) + 1 && $_POST['status'] == 'processed') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_shipped_counter']) + 1 && $_POST['status'] == 'shipped')) {
                            /* process the refer and earn */
                            $user = fetch_details('orders', ['id' => $order_item_res[0]['order_id']], 'user_id');
                            $user_id = $user[0]['user_id'];
                            $response = process_referral_bonus($user_id, $order_item_res[0]['order_id'], $_POST['status']);
                        }
                    }
                }
                $settings = get_settings('system_settings', true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $user_res = fetch_details('users', ['id' => $user_id], 'username,fcm_id');
                $fcm_ids = array();
                if (!empty($user_res[0]['fcm_id'])) {
                    $fcmMsg = array(
                        'title' => "Order status updated",
                        'body' => 'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['status'] . ' for your order ID #' . $order_item_res[0]['id'] . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '',
                        'type' => "order"
                    );

                    $fcm_ids[0][] = $user_res[0]['fcm_id'];
                    send_notification($fcmMsg, $fcm_ids);
                }

                $seller_res = fetch_details('users', ['id' => $order_item_res[0]['seller_id']], 'username,fcm_id');
                $fcm_ids = array();
                if (!empty($seller_res[0]['fcm_id'])) {
                    $fcmMsg = array(
                        'title' => "Order status updated",
                        'body' => 'Hello Dear ' . $seller_res[0]['username'] . ' order status updated to ' . $_POST['status'] . ' for your order ID #' . $order_item_res[0]['id'] . ' please take note of it! Regards ' . $app_name . '',
                        'type' => "order"
                    );

                    $fcm_ids[0][] = $seller_res[0]['fcm_id'];
                    send_notification($fcmMsg, $fcm_ids);
                }

                $this->response['error'] = false;
                $this->response['message'] = 'Status Updated Successfully';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access not allowed!';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
    }
    // delete_receipt
    function delete_receipt()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (empty($_GET['id'])) {
                $response['error'] = true;
                $response['message'] = 'Something went wrong';
            }
            if (delete_details(['id' => $_GET['id']], "order_bank_transfer")) {
                $response['error'] = false;
                $response['message'] = 'Deleted Successfully';
            } else {
                $response['error'] = true;
                $response['message'] = 'Something went wrong';
            }
            echo json_encode($response);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    function update_receipt_status()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            $this->form_validation->set_rules('order_id', 'Order Id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('user_id', 'User Id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('status', 'status', 'trim|required|xss_clean');

            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                $order_id = $this->input->post('order_id', true);
                $user_id = $this->input->post('user_id', true);
                $status = $this->input->post('status', true);

                if (update_details(['status' => $status], ['order_id' => $order_id], 'order_bank_transfer')) {
                    if ($status == 1) {
                        $status = "Rejected";
                    } else if ($status == 2) {
                        $status = "Accepted";
                    } else {
                        $status = "Pending";
                    }
                    $user = fetch_details("users", ['id' => $user_id], 'email,fcm_id');
                    send_mail($user[0]['email'], 'Bank Transfer Receipt Status.', 'Bank Transfer Receipt ' . $status . ' for order ID: ' . $order_id);
                    $fcm_ids[0][] = $user[0]['fcm_id'];
                    if (!empty($fcm_ids)) {
                        $fcmMsg = array(
                            'title' => 'Bank Transfer Receipt Status',
                            'body' =>  'Bank Transfer Receipt ' . $status . ' for order ID: ' . $order_id,
                            'type' => "bank_receipt_status"
                        );
                        send_notification($fcmMsg, $fcm_ids);
                    }
                    $this->response['error'] = false;
                    $this->response['message'] = 'Updtated Successfully';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something went wrong';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                }
            }

            print_r(json_encode($this->response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    function order_tracking()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'order-tracking';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Order Tracking | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Order Tracking | ' . $settings['app_name'];
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_order_tracking()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->Order_model->get_order_tracking_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_order_tracking()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->form_validation->set_rules('courier_agency', 'courier_agency', 'trim|required|xss_clean');
            $this->form_validation->set_rules('tracking_id', 'tracking_id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('url', 'url', 'trim|required|xss_clean');
            $this->form_validation->set_rules('order_id', 'order_id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('order_item_id', 'order item id', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                $order_id = $this->input->post('order_id', true);
                $order_item_id = $this->input->post('order_item_id', true);
                $courier_agency = $this->input->post('courier_agency', true);
                $tracking_id = $this->input->post('tracking_id', true);
                $url = $this->input->post('url', true);
                $data = array(
                    'order_id' => $order_id,
                    'order_item_id' => $order_item_id,
                    'courier_agency' => $courier_agency,
                    'tracking_id' => $tracking_id,
                    'url' => $url,
                );
                if (is_exist(['order_item_id' => $order_item_id, 'order_id' => $order_id], 'order_tracking', null)) {
                    if (update_details($data, ['order_id' => $order_id, 'order_item_id' => $order_item_id], 'order_tracking') == TRUE) {
                        $this->response['error'] = false;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = "Tracking details Update Successfuly.";
                    } else {
                        $this->response['error'] = true;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = "Not Updated. Try again later.";
                    }
                } else {
                    if (insert_details($data, 'order_tracking')) {
                        $this->response['error'] = false;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = "Tracking details Insert Successfuly.";
                    } else {
                        $this->response['error'] = true;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = "Not Inserted. Try again later.";
                    }
                }
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
