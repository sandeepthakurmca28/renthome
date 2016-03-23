<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
error_reporting(0);

define('CONSUMER_KEY', 'XL0NH88tuQYk0AfCH0M0mNltg');
define('CONSUMER_SECRET', 'NEUhU2CcmV2irO329tFMHDuvhpxusTV7zn1DAiXchfJndrUcYD');
define('OAUTH_CALLBACK', 'http://localhost/piplclone/authTwitter');
include_once('piplapis/search.php');
include_once('piplapis/data/fields.php');
ini_set('max_execution_time', 0);

class Start extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->library("simple_html_dom");
        $this->load->library('ion_auth');
        $this->load->library('session');
        //$this->load->library('twitteroauth');
    }

    public function index()
    {
        if ($this->ion_auth->logged_in())
        {
            $arr['session']['user'] = 1;
        }
        else
            $arr['session']['user'] = 0;

        $arr['page'] = 'home';
        $this->load->view('vwHome', $arr);
    }

    public function logout()
    {
        $this->ion_auth->logout();
        $this->session->unset_userdata('sessiondata');
        redirect('/signin', 'refresh');
    }

    // log the user in
    function login()
    {
        $this->form_validation->set_rules('identity', 'Email', 'required');
        $this->form_validation->set_rules('password', 'Password', 'required');
        if ($this->form_validation->run() == FALSE)
        {
            $this->form_validation->set_error_delimiters('<div class="alert alert-danger">', '</div>');
            $this->load->view('vwSignin', array());
        }
        else
        {
            $password = $_POST['password'];
            $identity = $_POST['identity'];
            $remember = TRUE; // remember the user
            $loggedIn = $this->ion_auth->login($identity, $password, $remember);

            if ($loggedIn !== FALSE)
            {
                $this->ion_auth->login($identity, $password, $remember);
                redirect('/myProfile', 'refresh');
            }
            else
            {
                $this->session->set_flashdata('error_msg', 'Please enter correct email/password.');
                redirect('/signin');
            }
        }
    }

    // create a new user
    function create_user()
    {
        $arr = array();
        $this->form_validation->set_rules('first_name', 'First Name', 'required|min_length[5]|max_length[20]');
        $this->form_validation->set_rules('last_name', 'Last Name', 'required');
        $this->form_validation->set_rules('identity', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required');
        if ($this->form_validation->run() == FALSE)
        {
            $this->form_validation->set_error_delimiters('<div class="alert alert-danger">', '</div>');
            $this->load->view('vwSignup', $arr);
        }
        else
        {
            $userTemp = explode('@', $_POST['identity']);
            $username = $userTemp[0];
            $password = $_POST['password'];
            $email = $_POST['identity'];
            $code = rand(10000, 10000000);
            $additional_data = array(
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'activation_code' => $code,
                'active' => '0'
            );
            $group = array('2'); // Sets user to admin.
            $registered = $this->ion_auth->register($username, $password, $email, $additional_data, $group);
            if ($registered !== FALSE)
            {
                $this->load->library('email');
                $this->load->helper('string');
                $config['protocol'] = 'smtp';
                $config['smtp_host'] = 'smtp.sendgrid.net';
                $config['smtp_port'] = '587';
                $config['smtp_user'] = 'rakesh_gupta';
                $config['smtp_pass'] = 'nokiatune1';
                $config['charset'] = 'utf-8';
                $config['newline'] = "\r\n";
                $config['mailtype'] = 'html'; // or html
                $config['validation'] = TRUE;
                $this->email->initialize($config);
                $this->email->from('people@gmail.com', 'People');
                $this->email->to($email);
                $this->email->subject('Signup Request for People');
                $htmlMessage = "<html>
                        <h3>Dear " . $additional_data['first_name'] . " , </h3>
                        <p> You have created an account with the below information :</p>
                        <p>
                            Email : $email <br />
                            Password : $password <br />
                        </p>
                        <p> Please click on the activate now link to activate your account. </p>                      
                        <p><a href='" . base_url() . "activation?code='" . $code . "'> Activate now</a></p>                        
                        <br />
                        <p> Thank You </p><br/>
                        <p> People</p></html>";
                $this->email->message($htmlMessage);
                if ($this->email->send())
                {
                    $this->session->set_flashdata('success_msg', 'Thanks for registering with us. Please click on the link received in your email.');
                    redirect('/signin');
                }
            }
            else
                redirect('/signup', 'refresh');
        }
    }

    public function authTwitter()
    {

        if (isset($_REQUEST['oauth_token']) && $_SESSION['token'] == $_REQUEST['oauth_token'])
        {

            //Successful response returns oauth_token, oauth_token_secret, user_id, and screen_name
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['token'], $_SESSION['token_secret']);
            $access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
            if ($connection->http_code == '200')
            {
                //Redirect user to twitter
                $_SESSION['status'] = 'verified';
                $_SESSION['request_vars'] = $access_token;

                //Insert user into the database
                $user_info = $connection->get('account/verify_credentials');
                $name = explode(" ", $user_info->name);
                $fname = isset($name[0]) ? $name[0] : '';
                $lname = isset($name[1]) ? $name[1] : '';
                $db_user = new Users();
                $db_user->checkUser('twitter', $user_info->id, $user_info->screen_name, $fname, $lname, $user_info->lang, $access_token['oauth_token'], $access_token['oauth_token_secret'], $user_info->profile_image_url);

                //Unset no longer needed request tokens
                unset($_SESSION['token']);
                unset($_SESSION['token_secret']);
                header('Location: index.php');
            }
            else
            {
                die("error, try again later!");
            }
        }
    }

    public function loginTwitter()
    {
        //Fresh authentication
        $connection = $this->twitteroauth->TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
        $request_token = $connection->getRequestToken(OAUTH_CALLBACK);

        //Received token info from twitter
        $_SESSION['token'] = $request_token['oauth_token'];
        $_SESSION['token_secret'] = $request_token['oauth_token_secret'];

        //Any value other than 200 is failure, so continue only if http code is 200
        if ($connection->http_code == '200')
        {
            //redirect user to twitter
            $twitter_url = $connection->getAuthorizeURL($request_token['oauth_token']);
            header('Location: ' . $twitter_url);
        }
        else
        {
            die("error connecting to twitter! try again later!");
        }
    }

    public function search()
    {
        $arr['page'] = 'search';
        $param = '';
        $mobile_search_var = $email_search_var = '';
        $email_search_var = $this->input->post('email');
        $mobile_search_var = $this->input->post('mobile');
        $insert = array();
        $displayData = array();
        if (empty($mobile_search_var) && empty($email_search_var))
        {
            redirect(base_url());
        }

        if ($email_search_var != '')
        {
            $sql = "SELECT * FROM tbl_users WHERE email = ?  AND user_status = ?";
            $search_result_db = $this->db->query($sql, array($email_search_var, 'A'))->row_array();
        }
        elseif ($mobile_search_var != '')
        {
            $sql = "SELECT * FROM tbl_users WHERE   phone_mobile like ? AND user_status = ?";
            $search_result_db = $this->db->query($sql, array("%$mobile_search_var%", 'A'))->row_array();
        }
        if (!empty($search_result_db) && count($search_result_db) > 0)
        {

            $articlesArray = array();
            $displayData = array();
            $insert = array();
            $data = array();
            $data['id'] = $search_result_db['id'];
            $articlesArray[0]['title'] = "";
            $articlesArray[2]['referer2title'] = "";
            $insert['email'] = $email_search_var;
            $insert['avatar'] = $search_result_db['avatar'];
            $articlesArray[0]['image'] = $search_result_db['avatar'];
            $articlesArray[0]['title'] = "";
            $articles['href'] = $search_result_db['linkedin_url'];
            $insert['first_name'] = $articles['first_name'] = $search_result_db['first_name'];
            $insert['last_name'] = $articles['last_name'] = $search_result_db['last_name'];
            $articles['referer1'] = $search_result_db['address_street'];
            $articles['address_city'] = $search_result_db['address_city'];
            $articles['address_state'] = $search_result_db['address_state'];
            $articles['address_country'] = $search_result_db['address_country'];
            $displayData['Linkedin'] = $insert['linkedin_url'] = $search_result_db['linkedin_url'];
            $insert['phone_mobile'] = $search_result_db['phone_mobile'];
            $insert['username'] = $search_result_db['user_name'];
            $displayData['Facebook'] = $search_result_db['fb_url'];
            $insert['fb_url'] = $search_result_db['fb_url'];
            $insert['career'] = $search_result_db['career'];
            $insert['business'] = $search_result_db['business'];
            $insert['education'] = $search_result_db['education'];
            $insert['place'] = $search_result_db['place'];
            $insert['linkedin_url'] = $search_result_db['linkedin_url'];
            $displayData['Twitter'] = $insert['twitter_url'] = $search_result_db['twitter_url'];
            $displayData['Instagram'] = $insert['instagram'] = $search_result_db['instagram'];
        }
        else
        {
            if ($email_search_var != '')
            {
                $param = $email_search_var;
                $insert['email'] = $data['email'] = $param;
                $articlesArray = array();
                $resultArray = $this->getImportAllData($param);
                
                $displayData = $this->curl($param);
                $insert['linkedin_url'] = !empty($resultArray['linkedin_link']) ? $resultArray['linkedin_link'] : $displayData['Linkedin'];
                $insert['fb_url'] = $displayData['Facebook'];
                $insert['twitter_url'] = $displayData['Twitter'];
                $insert['instagram'] = $displayData['Instagram'];
            }
            if ($mobile_search_var != '')
            {
                $param = $this->input->post('mobile');
                $insert['phone_mobile'] = $data['phone_mobile'] = $param;
                $truecallerData = $this->sendRequest($param);
                $displayData = $this->curl($param);
                 echo "<pre>";
            print_r($displayData);
            die;
                if (!empty($truecallerData) && is_array($truecallerData))
                {
                    $insert = $truecallerData;
                }
            }
            echo "<pre>";
            print_r($insert);
            die;
            $this->db->insert('tbl_users', $insert);
            $data['id'] = $this->db->insert_id();
        }

        // Downloading page to variable $scraped_page
        $data['insert'] = $insert;
        $data['displayData'] = $displayData;
        $data['articlesArray'] = $articlesArray;
        $this->load->view('vwSearch', $data);
    }

    // Defining the basic cURL function
    function curl($param)
    {
        $url = trim(urlencode("http://lullar-com-3.appspot.com/en/q=" . $param));
        $url = "http://lullar-com-3.appspot.com/en/?q=" . $param;
        $options = Array(
            CURLOPT_RETURNTRANSFER => TRUE, // Setting cURL's option to return the webpage data
            CURLOPT_FOLLOWLOCATION => TRUE, // Setting cURL to follow 'location' HTTP headers
            CURLOPT_AUTOREFERER => TRUE, // Automatically set the referer where following 'location' HTTP headers
            CURLOPT_CONNECTTIMEOUT => 1000, // Setting the amount of time (in seconds) before the request times out
            CURLOPT_TIMEOUT => 20000, // Setting the maximum amount of time for cURL to execute queries
            CURLOPT_MAXREDIRS => 10, // Setting the maximum number of redirections to follow
            CURLOPT_USERAGENT => 'User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111 Safari/537.36', // Setting the useragent
            CURLOPT_URL => $url, // Setting cURL's URL option with the $url variable passed into the function
        );
        $ch = curl_init();  // Initialising cURL 
        curl_setopt_array($ch, $options);   // Setting cURL's options using the previously assigned array data in $options
        $scraped_page = curl_exec($ch); // Executing the cURL request and assigning the returned data to the $data variable
        curl_close($ch);   // Closing cURL 
        $scraped_data = $this->scrape_between($scraped_page, "<body>", "</body>");   // Scraping downloaded dara in $scraped_page for content between <title> and </title> tags
        $html = $this->simple_html_dom->load($scraped_data);
        $html->find("div", 2)->last_child()->first_child()->first_child();
        $html->save();
        $articles['Instagram'] = $html->find('div[id=instagram]', 0)->find('a', 0)->getAttribute('href');
        $articles['Facebook'] = $html->find('div[id=facebook]', 0)->find('a', 0)->getAttribute('href');
        $articles['Twitter'] = $html->find('div[id=twitter]', 0)->find('a', 0)->getAttribute('href');
        $articles['Youtube'] = $html->find('div[id=youtube]', 0)->find('a', 0)->getAttribute('href');
        $articles['Orkut'] = $html->find('div[id=orkut]', 0)->find('a', 0)->getAttribute('href');
        $articles['Linkedin'] = $html->find('div[id=linkedin]', 0)->find('a', 0)->getAttribute('href');
        return $articles;   // Returning the data from the function
    }

    function scrape_between($data, $start, $end='')
    {
        $data = stristr($data, $start); // Stripping all data from before $start
        $data = substr($data, strlen($start));  // Stripping $start
        $stop = stripos($data, $end);   // Getting the position of the $end of the data to scrape
        $data = substr($data, 0, $stop);    // Stripping all data from after and including the $end of the data to scrape
        return $data;   // Returning the scraped data from the function
    }

    public function signup()
    {
        $arr['page'] = 'Signup';
        $arr['active'] = 'signup';
        $this->load->view('vwSignup', $arr);
    }

    public function signin()
    {
        $arr['page'] = 'Signin';
        $arr['active'] = 'signin';
        $this->load->view('vwSignin', $arr);
    }

    public function forgot()
    {
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_error_delimiters('<div class="alert alert-danger">', '</div>');

        if ($this->form_validation->run() !== FALSE)
        {

            $email = $this->input->post('email');

            $sql = "SELECT * FROM users WHERE email = '" . $email . "'";
            $val = $this->db->query($sql);
            if ($val->num_rows > 0)
            {
                $this->load->library('email');
                $this->load->helper('string');
                $config['protocol'] = 'smtp';
                $config['smtp_host'] = 'smtp.sendgrid.net';
                $config['smtp_port'] = '587';
                $config['smtp_user'] = 'rakesh_gupta';
                $config['smtp_pass'] = 'nokiatune1';
                $config['charset'] = 'utf-8';
                $config['newline'] = "\r\n";
                $config['mailtype'] = 'html'; // or html
                $config['validation'] = TRUE;
                $this->email->initialize($config);
                $this->email->from('rahul.3ginfo@gmail.com', 'People');
                $this->email->to($email);
                $this->email->subject('Password Reset Information');
                $resetcode = uniqid();

                $htmlMessage = "<html>
                                    <h3>Dear " . $email . " , </h3>
                                    <p> Please click on below link to reset password </p>                      
                                    <p>
                                       Link : " . base_url() . 'reset_password/' . $resetcode . " <br />
                                    </p>

                                    <br />
                                    <p> Thank You </p>
                                    People</html>";

                $this->email->message($htmlMessage);
                if ($this->email->send())
                {
                    $this->db->update('users', array('forgotten_password_code' => $resetcode), array('email' => $email));
                    $this->session->set_flashdata('msg', 'Please check your email to reset password');
                    $this->session->set_flashdata('class', 'success');
                    redirect(base_url() . 'forgot');
                }
                else
                {
                    $this->session->set_flashdata('msg', 'Some error occured. Please try after some time.');
                    $this->session->set_flashdata('class', 'danger');
                    redirect(base_url() . 'forgot');
                }
            }
        }
        else
        {

            $arr['page'] = 'Forgot';
            $this->load->view('vwForgot', $arr);
        }
    }

    public function reset_password()
    {

        $forgotten_password_code = $this->uri->segment(2);
        if (empty($forgotten_password_code))
        {
            redirect(base_url() . 'forgot');
        }
        elseif ($forgotten_password_code)
        {
            $query = $this->db->get_where('users', array('forgotten_password_code' => $forgotten_password_code));
            $result = $query->result();
            if (!empty($result) && count($result) > 0)
            {
                $this->form_validation->set_rules('password', 'Password', 'required|min_length[5]|max_length[30]');
                $this->form_validation->set_rules('password', 'Password', 'required|matches[passconf]');
                $this->form_validation->set_rules('passconf', 'Password Confirmation', 'required');
                $this->form_validation->set_error_delimiters('<div class="alert alert-danger">', '</div>');
                if ($this->form_validation->run() !== FALSE)
                {
                    $this->ion_auth->reset_password_new($this->input->post('password'), $result[0]->email);
                    $this->session->set_flashdata('msg', 'Please login with new password');
                    $this->session->set_flashdata('class', 'success');
                    redirect(base_url() . 'signin');
                }
                else
                {
                    $arr['page'] = 'Reset Password';
                    $this->load->view('vwResetPassword', $arr);
                }
            }
            else
            {
                $this->session->set_flashdata('msg', 'This link has been expired. Please try again to reset password');
                $this->session->set_flashdata('class', 'danger');
                redirect(base_url() . 'forgot');
            }
        }
    }

    /* public function reset_password()
      {
      $email = $this->input->post('email');
      $response = array('status' => 0, 'message' => '');
      if (!empty($email))
      {
      $sql = "SELECT * FROM users WHERE email = '" . $email . "'";
      $val = $this->db->query($sql);
      if ($val->num_rows > 0)
      {
      $this->load->library('email');
      $this->load->helper('string');
      $config['protocol'] = 'smtp';
      $config['smtp_host'] = 'smtp.sendgrid.net';
      $config['smtp_port'] = '587';
      $config['smtp_user'] = 'rakesh_gupta';
      $config['smtp_pass'] = 'nokiatune1';
      $config['charset'] = 'utf-8';
      $config['newline'] = "\r\n";
      $config['mailtype'] = 'html'; // or html
      $config['validation'] = TRUE;
      $this->email->initialize($config);
      $this->email->from('rahul.3ginfo@gmail.com', 'People');
      $this->email->to($email);
      $this->email->subject('Password Reset Information');
      $newPassword = random_string('alnum', 20);
      $reset = $this->ion_auth->reset_password($email, $newPassword);
      if ($reset)
      {
      $htmlMessage = "<html>
      <h3>Dear " . $email . " , </h3>
      <p> Please find your new credentials to login </p>
      <p>
      Email : $email <br />
      Password : $newPassword <br />
      </p>
      <p>You can reset your password by loging into the system</p>
      <br />
      <p> Thank You </p>
      People</html>";
      $this->email->message($htmlMessage);
      if ($this->email->send())
      {
      $response['status'] = 1;
      $response['message'] = "Your password has been reset. Please check your email";
      }
      }
      }
      else
      {
      $response['message'] = "Your email id doesn't exists";
      }
      }
      else
      {
      $response['message'] = "Please provide email to continue";
      }
      echo json_encode($response);
      die;
      } */

    public function saveProfile()
    {
        $id = $this->input->post('id');
        $additional_data = array(
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
        );
        if (!empty($_FILES['image']['name']))
        {
            $config = array(
                'upload_path' => "uploads/images/",
                'allowed_types' => "gif|jpg|png|jpeg",
                'overwrite' => TRUE,
                'max_size' => "2048000", // Can be set to particular file size , here it is 2 MB(2048 Kb)
            );
            $new_name = $id . '_' . time();
            $config['file_name'] = $new_name;
            $this->load->library('upload', $config);
            $this->upload->initialize($config);
            if ($this->upload->do_upload('image'))
            {
                $upload_array = $this->upload->data();
                $additional_data['image'] = $upload_array["file_name"];
            }
            else
            {
                $error = $this->upload->display_errors();
                $this->session->set_flashdata('error_msg', $error);
                redirect('/myProfile');
            }
        }
        $this->ion_auth->update($id, $additional_data);
        $this->session->set_flashdata('success_msg', 'Profile data has been updated successfully.');
        redirect('/myProfile');
    }

    public function change_password()
    {
        $identity = $this->session->userdata['email'];
        $oldPassword = $this->input->post('old_password');
        $newPassword = $this->input->post('new_password');
        $confirmPassword = $this->input->post('confirm_password');
        if ($newPassword != $confirmPassword)
        {
            $this->session->set_flashdata('error_msg', 'Confirm Password is not matched with the New Password');
            redirect('/changePassword');
        }
        else
        {
            $result = $this->ion_auth->change_password($identity, $oldPassword, $newPassword);
            $this->session->set_flashdata('success_msg', 'Password has been changed successfully.');
            redirect('/changePassword');
        }
    }

    public function subscription()
    {
        if ($this->ion_auth->logged_in())
        {
            $arr['session']['user'] = 1;
        }
        else
            redirect('/signin', 'refresh');

        $this->ion_auth->user($this->session->userdata['user_id'])->row();
        $arr['page'] = 'subscription';
        $this->load->view('vwSubscription', $arr);
    }

    public function paypal()
    {
        if ($this->ion_auth->logged_in())
        {
            $arr['session']['user'] = 1;
        }
        else
            $arr['session']['user'] = 0;

        $dataService = $this->input->post('data_service');
        $fileService = $this->input->post('file_service');

        $sql = "select * from tbl_services";
        $val = $this->db->query($sql);

        foreach ($val->result_array() as $row)
        {
            if ($row['id'] == '1')
                $amountDataService = $dataService * $row['cost_per_api_request'];
            elseif ($row['id'] == '2')
                $amountFileService = $fileService * $row['cost_per_email'];
        }

        $total = $amountDataService + $amountFileService;
        $user = $this->ion_auth->user($this->session->userdata['user_id'])->row();
        $sql = "insert into tbl_order (package_id, package_cost,status,create_date,user_id) values ('Service', '" . $total . "', '1' ,'" . date('Y-m-d H:i:s') . "', '" . $user->id . "')";
        $val = $this->db->query($sql);
        $insert_id = $this->db->insert_id();

        $sql = "insert into tbl_orderdetails (service_id, order_id,qty) values ('1', '" . $insert_id . "', '" . $dataService . "')";
        $val = $this->db->query($sql);

        $sql = "insert into tbl_orderdetails (service_id, order_id,qty) values ('2', '" . $insert_id . "', '" . $fileService . "')";
        $val = $this->db->query($sql);


        $arr['page'] = 'subscription';
        $arr['amount'] = $amountDataService + $amountFileService;

        $arr['id'] = $insert_id;
        $this->load->view('vwRedirect', $arr);
    }

    public function ipn()
    {
        $ipn = json_encode($_REQUEST);
        $sql = "update tbl_order set status= 1 ";
        $val = $this->db->query($sql);
        //file_put_contents('text.txt',$_REQUEST);
        if ($ipn['payment_status'])
        {

            $sql = "update tbl_order set status= 1 where id = '" . $ipn['item_number1'] . "'";
            $val = $this->db->query($sql);
        }
    }

    public function payment_complete()
    {
        $ipn = $_REQUEST;
        $item_num = $ipn['item_number'];
        $sql = "update tbl_order set status='1' where id='" . $item_num . "'";
        $val = $this->db->query($sql);
        $arr['pageTitle'] = "Profile";
        $arr['active'] = 'profile';
        $arr['users'] = $this->ion_auth->user($this->session->userdata['user_id'])->row();
        //$this->load->view('vwProfile', $arr);
        $this->load->view('vwPaymentComplete', $arr);
    }

    public function activation()
    {
        $numCode = $this->input->get('code');
        if ($numCode == '')
        {
            $_SESSION['msg'] = base64_encode('Invalid Activation Code!');
            redirect('/signin');
        }
        $sql = "select * from users where activation_code= '" . $numCode . "' and active=0";
        $user = $this->db->query($sql);
        if ($user->num_rows >= 1)
        {
            $updateSql = "update users set active= '1',activation_code='' where activation_code = '" . $numCode . "'";
            $result = $this->db->query($updateSql);
            if ($result)
            {
                $this->session->set_flashdata('success_msg', 'You have successfully activated your account.');
                redirect('/signin');
            }
        }
        else
        {
            $_SESSION['msg'] = $this->session->set_flashdata('error_msg', 'Invalid Activation Code!');
            redirect('/signin');
        }
    }

    public function changePassword()
    {
        $arr['pageTitle'] = "Change Password";
        $arr['active'] = 'changePassword';
        $this->load->view('vwChangePassword', $arr);
    }

    public function profile()
    {
        $arr['pageTitle'] = "Profile";
        $arr['active'] = 'profile';
        $arr['users'] = $this->ion_auth->user($this->session->userdata['user_id'])->row();
        $this->load->view('vwProfile', $arr);
    }

    public function myProfile()
    {
        $arr['pageTitle'] = "Update Profile Information";
        $arr['users'] = $this->ion_auth->user($this->session->userdata['user_id'])->row();
        $arr['active'] = 'myProfile';
        $this->session->userdata['avatar'] = $arr['users']->image;
        $this->load->view('vwMyProfile', $arr);
    }

    public function myContacts()
    {
        $arr['pageTitle'] = "My Contacts";
        $arr['active'] = 'myContacts';
        $sql = "select tu.id,tu.first_name,tu.email,tu.avatar,tu.phone_mobile,tu.fb_url,tu.twitter_url,tu.linkedin_url 
                from tbl_users as tu
                join user_contacts as uc on uc.contacted_user_id=tu.id
                where uc.user_id='" . $this->session->userdata['user_id'] . "'";
        $query = $this->db->query($sql);
        $contacts = array();
        if ($query->num_rows >= 1)
        {
            foreach ($query->result() as $row)
            {
                $contacts[] = $row;
            }
        }
        $arr['contacts'] = $contacts;
        $this->load->view('vwMyContacts', $arr);
    }

    public function myService()
    {
        $arr['pageTitle'] = "My Service";
        $arr['active'] = 'myService';
        $sql = "select ts.service_name,tsp.service_id,torder.id,tsp.qty,ts.cost_per_api_request,ts.cost_per_email from tbl_services as ts 
                join tbl_orderdetails as tsp on tsp.service_id=ts.id
                join tbl_order as torder on torder.id=tsp.order_id
                where torder.user_id='" . $this->session->userdata['user_id'] . "' order by torder.id desc";

        $query = $this->db->query($sql);
        $services = array();
        if ($query->num_rows >= 1)
        {
            foreach ($query->result() as $row)
            {
                // if ($row->service_id == 1)
                //{
                $services[] = $row;
                //}
            }
        }

        $arr['services'] = $services;
        $this->load->view('vwMyService', $arr);
    }

    public function support()
    {
        $arr['pageTitle'] = "Support";
        $arr['active'] = 'support';
        $this->load->view('vwSupport', $arr);
    }

    public function addcontact()
    {
        $insert['user_id'] = $this->session->userdata['user_id'];
        $insert['contacted_user_id'] = $this->input->post('id');
        $response = array('status' => 0, 'message' => '');
        $sql = "SELECT * FROM user_contacts WHERE user_id = '" . $insert['user_id'] . "' and contacted_user_id='" . $insert['contacted_user_id'] . "'";
        $val = $this->db->query($sql);
        if ($val->num_rows <= 0)
        {
            $inserted = $this->db->insert('user_contacts', $insert);
            if ($inserted)
            {
                $response['status'] = 1;
                $response['message'] = 'Contact added to list successfully.';
            }
            else
            {
                $response['message'] = 'Some error occured.';
            }
        }
        else
        {
            $response['status'] = 1;
            $response['message'] = 'Contact already added in list.';
        }
        echo json_encode($response);
        die;
    }

    function sendRequest($phoneNumber)
    {
        $baseURL = "www.truecaller.com/in/";
        $finalURL = $baseURL . $phoneNumber;
        $requestURL = $finalURL;
        $curl = curl_init();
        $setUserAgent = "Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36";
        $setCookie = "fbm_126694440681943=base_domain=.truecaller.com; PHPSESSID=2lbu8i6i73r5q9m4ud7eoikud1; __gads=ID=68f128aa84d8a063:T=1409568878:S=ALNI_MYFxzZnrdLrgqKwPuCm7QlwGM8sUg; XLBS=XLBS|VARUF|VARQG; __utma=99565659.708189400.1409568750.1409668083.1409668083.1; __utmc=99565659; __utmz=99565659.1409668083.1.1.utmcsr=apps.facebook.com|utmccn=(referral)|utmcmd=referral|utmcct=/truecaller/; user=eyJpdiI6InBoWmUwQjQ1RklIT2lVS2F0QzZ1VVJxTHBIb1wvQU9VanEzc0UxdnNkNkxBPSIsInZhbHVlIjoiUzFPUmhWZzA1SmxpRkJBeGZwczNlbHgzelNHTXJhZG1uN3l6N0dkdG1YejJlNWZ1VG5oWTNnNmNrTmFQNVdGdkRoOVBibUhHaEh4cHJlSFJ4dDVIYTdcL3NGZE00OWh0a2pLTzhjc2s5bW5qVU1aRldhNWI5TURpVDZiYlwvWVlBTGFnVGdhVEpDdUM4bmpmQ295a21TT2FFMm5UUzhiem9JSk94RXh3TE1HWEMxTzhxZ29aa1d3S055Z3BFbEQzbExhMFc1ZWFYYll6c2gwVWFEaUpxVzBybXBBUVpoOHE3K2ZTVTVpM1hrM2VndVErb0J2RmpwYmptUDNZNXhJeEQzVmdJbUlZNndPaEdZR0Z6QVRuRTVMeFd5djZoT3EyeEVMZGRTWEhuREY4ajh1NWxnWDllZmxLVHVWQUludElQbTg2eldSeXRQaWZNYThMcmFGVlRnRUcrYmR2azRoTjRuRVJ3VjdBY2hoZ0FLTTM3bmFpTjd5SERPQUllZjVEdUM4dDFjSllcL28yYmUzYTYxTGRKRjBSM3g3NnVVcFlvMGltajFsUlwvNG00M2JqZVpESzZyajJFY2xERXptaStDeXI4VmE2YTk3SlkxaHMxZmlmNVVRT2NIXC9YWjVcL3JjRkFCK21oWFRScTJUUnUzTVJLWHpLNDUyajdYTkx5M2JkK05FWWNMa3I4TlFEUlZ5bmVMdjd2MHdlZ2xOY00zMXRxdnk1ZjRcL0k1a1RzaTBcL2czc0VPcnhFeHpMOTM0Wmp3YVwvQzRQaVVIa2Y2b0ZUbms4a0FaRkR3bk53b2dkNzc2YitnT0hLVHRSM0MrNjkrTXczOUx0VmlmbWtHMzU3MmgrQ2I3eDNqVGUyQlo2RFVwZlRkc0JtNXNmMXdRY1Z2Q1VqSjVuVzFjUko2em9CVXQ4eWRKSVlcL2J3bFJkbXg0XC9DbFc3M0YycDdGcEpUS2piYzRmRkR0aFdUalwvMW40MVBtVkwwaUZJSm5WbFNoQlVhU2s5VW1VRWpETEE5eU5yUUM3ZjZkY0JIRFJDdG1GMG5SYTBYdUVBcUs4dTRyVURVRXB0WXdORlFmeVZNVT0iLCJtYWMiOiI0ODUwN2MxODVjMjFiZGUzMzg3OGY2Y2VmMDU4NWU0MDA3NmMyMmRhZjBiNDcwNTU0YzVlNzVhZDgzMjg0MzZlIn0%3D; _ga=GA1.2.708189400.1409568750; truecaller-session=eyJpdiI6IlhYS1hndjVTSlNwN3EyNFQ1cVY2UUt0UDZFR0gyZ1U3MnMxcnUrTDBrd3c9IiwidmFsdWUiOiIwOEpsdGhuYkRlcFU2QXl0azhqOGVMcENydkxQVDkzK1RqQktKS3ZJVlwvQk1ma0Z5b3ZXSkR3SFV0NHpDQ2taQ29WWWJyZXVINFwvRlhOSEZhRGV0dCtnPT0iLCJtYWMiOiIxNzFiZGIzZWU5NDllZWYzMDU4ZTkyNzQzNzc5MmFmOWZmMzE1NTdhMjk3ZjhlMzVjODM0ODUwZmZkMDZjNzcyIn0%3D; XLBS2=XLBS2|VAbqn|VAbqk";

        curl_setopt($curl, CURLOPT_URL, $requestURL);
        curl_setopt($curl, CURLOPT_USERAGENT, $setUserAgent);
        curl_setopt($curl, CURLOPT_COOKIE, $setCookie);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($curl) or die(curl_error($curl));
        $resultCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($resultCode == 200)
        {
            $scraped_page = $result;
            $scraped_data = $this->scrape_between($scraped_page, "<main>", "</main>");
            $html = $this->simple_html_dom->load($scraped_data);
            $html->save();
            if($html->find('div[class=detailView__nameText]',0))               
                $true['first_name'] = @trim($html->find('div[class=detailView__nameText]', 0)->innertext);
            if ($html->find('div[class=detailView__avatar]', 0)->find('img', 0))
                $true['avatar'] = $html->find('div[class=detailView__avatar]', 0)->find('img', 0)->getAttribute('src');
            if ($html->find('div[id=location]', 0)->find('div[class=detailView__text]', 0)->find('a', 0))
                $true['address_map'] = $html->find('div[id=location]', 0)->find('div[class=detailView__text]', 0)->find('a', 0)->getAttribute('href');
            if ($html->find('div[id=location]', 0)->find('div[class=detailView__text]', 0)->find('a', 0))
                $address = $html->find('div[id=location]', 0)->find('div[class=detailView__text]', 0)->find('a', 0)->innertext;
            if (!empty($address))
            {
                $explode = explode(',', $address);
                $true['address_city'] = $explode[0];
                $true['address_country'] = $explode[1];
            }            
            if ($html->find('div[class=detailView__list]', 0)->find('div[class=detailView__col]', 0)->find('div[class=detailView__item]', 0)->find('h1', 0))
                $true['phone_mobile'] = $html->find('div[class=detailView__list]', 0)->find('div[class=detailView__col]', 0)->find('div[class=detailView__item]', 0)
                                ->find('h1', 0)->innertext;
            if ($html->find('div[class=detailView__list]', 0)->find('div[class=detailView__col]', 0)->find('div[class=detailView__item]', 1))
                $true['email'] = $html->find('div[class=detailView__list]', 0)->find('div[class=detailView__col]', 0)->find('div[class=detailView__item]', 1)
                                ->find('a', 0)->innertext;
            echo "<pre>";
            printR($true);
            die;
            return $true;
        }
        else
        {
            return $resultCode;
        }
    }

    function getImportiodata($email='')
    {
        $piplApi = urlencode("http://pipl.com/search/?q=" . $email . "&l=&sloc=&in=5");
        $apiKey = "8957283e4069401e83d0c3d31be193824d393424eae11bea180a24e775113388d4563d73823af1a4da5b0710dc266175c6da695ffa88adfc1fbcacea8daddc8e669de0394e33acbded15de9d02fe2433";
        $connectorGuid = "798aafba-ec1a-4b23-818c-f0f2a575edb1";
        $params = 'input=webpage/url:' . $piplApi . '&_apikey=' . $apiKey;
        $url = "http://api.import.io/store/connector/" . $connectorGuid . "/_query?" . $params;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "import-io-client: import.io PHP client",
            "import-io-client-version: 2.0.0",
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_FRESH_CONNECT, 1);
        $result = curl_exec($ch);
        $displayData = array();
        if (!empty($result))
        {
            $resultArray = json_decode($result);
            $response = array();
            if (!empty($resultArray->outputProperties))
            {
                foreach ($resultArray->outputProperties as $obj)
                {
                    $response[] = $obj->name;
                }
            }
            if (!empty($resultArray->results[0]))
            {
                $data = $resultArray->results[0];
                if (!empty($data))
                {
                    foreach ($response as $label)
                    {
                        $displayData[$label] = $data->$label;
                    }
                }
            }
        }
        $insert['first_name'] = '';
        if (!empty($resultArray['name']))
            $insert['first_name'] = $resultArray['name'];
        else if (!empty($resultArray['linkedin_name']))
            $insert['first_name'] = $resultArray['linkedin_name'];
        else if (!empty($resultArray['gplus_name']))
            $insert['first_name'] = $resultArray['gplus_name'];
        else if (!empty($resultArray['related_1_name']))
            $insert['first_name'] = $resultArray['related_1_name'];
        $insert['career'] = !empty($resultArray['career']) ? $resultArray['career'] : '';
        $insert['education'] = !empty($resultArray['education']) ? $resultArray['education'] : '';
        $insert['place'] = !empty($resultArray['place']) ? $resultArray['place'] : '';
        $insert['business'] = !empty($resultArray['related_2_business']) ? $resultArray['related_2_business'] : '';
        $insert['avatar'] = '';
        if (!empty($resultArray['image']))
            $insert['avatar'] = $resultArray['image'];
        else if (!empty($resultArray['linkedin_image']))
            $insert['avatar'] = $resultArray['linkedin_image'];
        else if (!empty($resultArray['gplus_image']))
            $insert['avatar'] = $resultArray['gplus_image'];
        if (!empty($resultArray['gplus_link']) && empty($insert['avatar']))
        {
            $insert['avatar'] = $this->importImage($resultArray['gplus_link']);
        }
        if(empty($insert['avatar']))
        {
            $searchPar = 'plus.google.com';
            if(!empty($resultArray['img1_link']) && strpos($resultArray['img1_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img1_link']);
            }
            if(empty($insert['avatar']) && !empty($resultArray['img2_link']) && strpos($resultArray['img2_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img2_link']);
            }
            if(empty($insert['avatar']) && !empty($resultArray['img3_link']) && strpos($resultArray['img3_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img3_link']);
            }
            if(empty($insert['avatar']) && !empty($resultArray['img4_link']) && strpos($resultArray['img4_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img4_link']);
            }
            if(empty($insert['avatar']) && !empty($resultArray['img5_link']) && strpos($resultArray['img5_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img5_link']);
            }
            if(empty($insert['avatar']) && !empty($resultArray['img6_link']) && strpos($resultArray['img6_link'], $searchPar)!=false)
            {
                $insert['avatar'] = $this->importImage($resultArray['img6_link']);
            }
        }
}

    function importImage($googleApi)
    {
        $googleApi = urlencode("http://".$googleApi);
        $apiKey = "8957283e4069401e83d0c3d31be193824d393424eae11bea180a24e775113388d4563d73823af1a4da5b0710dc266175c6da695ffa88adfc1fbcacea8daddc8e669de0394e33acbded15de9d02fe2433";
        $connectorGuid = "da6f18e6-0f4c-4755-a8f9-debba3f9b410";
        $params = 'input=webpage/url:' . $googleApi . '&&_apikey=' . $apiKey;
        $url = "http://api.import.io/store/connector/" . $connectorGuid . "/_query?" . $params;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "import-io-client: import.io PHP client",
            "import-io-client-version: 2.0.0",
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        $result = curl_exec($ch);
        if (!empty($result))
        {
            $resultArray = json_decode($result);
            $response = array();
            if (!empty($resultArray->results[0]))
            {
                $data = $resultArray->results[0];
                if (!empty($data))
                {
                    if (!empty($data->my_column))
                        return $data->my_column;
                }
            }
        }
        return '';
    }
    
    function getImportAllData($email='')
    {
        $piplApi = urlencode("http://pipl.com/search/?q=" . $email . "&l=&sloc=&in=5");
        $apiKey = "8957283e4069401e83d0c3d31be193824d393424eae11bea180a24e775113388d4563d73823af1a4da5b0710dc266175c6da695ffa88adfc1fbcacea8daddc8e669de0394e33acbded15de9d02fe2433";
        $connectorGuid = "22afc5fb-f96e-41c3-b46e-e1293e69015c";
        $params = 'input=webpage/url:' . $piplApi . '&_apikey=' . $apiKey;
        $url = "http://api.import.io/store/connector/" . $connectorGuid . "/_query?" . $params;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "import-io-client: import.io PHP client",
            "import-io-client-version: 2.0.0",
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $displayData = array();
        if (!empty($result))
        {
            $resultArray = json_decode($result);
            $response = array();
            if (!empty($resultArray->outputProperties))
            {
                foreach ($resultArray->outputProperties as $obj)
                {
                    $response[] = $obj->name;
                }
            }
            if (!empty($resultArray->results[0]))
            {
                $data = (array)$resultArray->results[0];
                
                if (!empty($data))
                {
                    if(!empty($data['my_column']))
                    {
                        $displayData['first_name'] = $data['my_column'];
                    }
                    if(!empty($data['title1']))
                    {
                        $displayData['career'] = $data['desc1'];
                    }
                    if(!empty($data['title2']))
                    {
                        $displayData['education'] = $data['desc2'];
                    }
                    if(!empty($data['title3']))
                    {
                        $displayData['username'] = $data['desc3/_text'];
                        $displayData['username_link'] = $data['desc3'];
                    }
                    if(!empty($data['title4']))
                    {
                        $displayData['phone_mobile'] = $data['desc4_/text'];
                    }
                    if(!empty($data['title5']))
                    {
                        $displayData['caradditional_nameeer'] = $data['desc5'];
                    }                    
                    if(!empty($data['title6']))
                    {
                        $displayData['place'] = $data['desc6'];
                    }
                    
                    if(!empty($data['title7']))
                    {
                        $displayData['associated_with'] = $data['desc7/_text'];
                        $displayData['associated_with_link'] = $data['desc7'];
                        
                    }
                    if(!empty($data['link1']))
                    {
                         $displayData = $this->checkLinks($data,$data['link1'])
                         checkLinks
                    }
                    
                    
                    
                }
            }
        }
    }
    
    function checkLinks($data,$linkVal)
    {
        $displayData = array();
        switch($linkVal)
        {
            case strpos($linkVal, 'linkedin')!= false):
                $displayData['linkedin_url'] = $data['link1'];
                if(empty($displayData['linkedin_url']))
                    $displayData['linkedin_url'] = $data['link1_title'];
                if(empty($displayData['linkedin_url']))
                    $displayData['linkedin_url'] = $data['link1_title/_source'];
                break;  
            case strpos($linkVal, 'facebook')!= false):
                $displayData['facebook_url'] = $data['link1'];
                if(empty($displayData['facebook_url']))
                    $displayData['facebook_url'] = $data['link1_title'];
                if(empty($displayData['facebook_url']))
                    $displayData['facebook_url'] = $data['link1_title/_source'];
                break;  
                
        }
        
    }
}