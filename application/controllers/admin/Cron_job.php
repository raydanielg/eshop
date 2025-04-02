<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cron_job extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['ion_auth', 'form_validation', 'upload']);
        $this->load->helper(['url', 'language', 'file']);
        $this->load->model(['Seller_model']);
    }

    public function settle_seller_commission()
    {
        $this->form_validation->set_data($this->input->get());
        $this->form_validation->set_rules('is_date', 'is_date', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $is_date = (isset($_GET['is_date']) && is_numeric($_GET['is_date']) && !empty(trim($_GET['is_date']))) ? $this->input->get('is_date', true) : false;
            
            return $this->Seller_model->settle_seller_commission($is_date);
        }
    }
}
