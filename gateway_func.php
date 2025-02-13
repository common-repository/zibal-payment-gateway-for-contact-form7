<?php

/*
Plugin Name: درگاه پرداخت زیبال برای فرم های Contact 7
Plugin URI: https://docs.zibal.ir/
Description: اتصال فرم های Contact Form 7 به درگاه پرداخت زیبال
Author: Yahya Kangi
Author URI: https://github.com/YahyaKng
Version: 1.0
*/

// global $wpdb;
// global $postid;


/**
 * @param $action (PaymentRequest, )
 * @param $params string
 *
 * @return mixed
 */
function zgcf7_postToZibal($action, $params)
{

    try {

        $number_of_connection_tries = 3;
        $response = null;
        while ( $number_of_connection_tries>0 ) {
            $response = wp_safe_remote_post('https://gateway.zibal.ir/v1/' . $action,array(
                'body'=> $params,
                'headers'=>array(
                    'Content-Type'=>'application/json'
                )
            ));

            if ( is_wp_error( $response ) ) {
                $number_of_connection_tries --;
                continue;
            } else {
                break;
            }
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    } catch (Exception $ex) {
        return false;
    }
}

function zgcf7_ZIBAL_CF7_relative_time($ptime)
{
    // date_default_timezone_set("Asia/Tehran");
    $etime = time() - $ptime;
    if ($etime < 1) {
        return '0 ثانیه';
    }
    $a = array(12 * 30 * 24 * 60 * 60 => 'سال',
        30 * 24 * 60 * 60 => 'ماه',
        24 * 60 * 60 => 'روز',
        60 * 60 => 'ساعت',
        60 => 'دقیقه',
        1 => 'ثانیه'
    );
    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? ' ' : '');
        }
    }
}


function zgcf7_result_payment_func($atts)
{
    global $wpdb;
    $Return_MessageEmail = '';
    if(isset($_GET['status'])) {

        $Return_Track_Id = sanitize_text_field($_GET['trackId']);
		$Status = sanitize_text_field($_GET['status']);
		$Success = sanitize_text_field($_GET['success']);

		$Theme_Message = get_option('cf7pp_theme_message', '');

		$theme_error_message = get_option('cf7pp_theme_error_message', '');

		$options = get_option('cf7pp_options');
		foreach ($options as $k => $v) {
			$value[$k] = $v;
		}

		$merchantId = sanitize_text_field($value['gateway_merchantid']);
		$sucess_color = sanitize_hex_color($value['sucess_color']);
		$error_color = sanitize_hex_color($value['error_color']);

		if ($Status == '2') {
			
			$table_name = $wpdb->prefix . 'zibal_contact_form_7';
			$cf_Form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE transid=" . $Return_Track_Id));
			if (null !== $cf_Form) {
				$Amount = $cf_Form->cost;
			}

			$data = array(
				'merchant' => $merchantId,
				'trackId' => $Return_Track_Id
			);

			$result = zgcf7_postToZibal('verify', json_encode($data));

			if ($result["result"] == 100) {
				$Return_MessageEmail = 'success';
			}
	} else {
		$Return_MessageEmail = 'error';
	}


    }
    


    if ($Return_MessageEmail == 'success') {
        $wpdb->update($wpdb->prefix . 'zibal_contact_form_7', array('status' => 'success', 'transid' => $wpdb->prepare(sanitize_text_field($_GET["trackId"]))), array('transid' => $wpdb->prepare($Return_Track_Id)), array('%s', '%s'), array('%d'));

        //Dispaly
        $body = '<b style="color:'.$sucess_color.';">'.stripslashes(str_replace('[transaction_id]', $wpdb->prepare(sanitize_text_field($_GET["trackId"])), $wpdb->prepare($Theme_Message))).'<b/>';
        return zgcf7_CreateMessage_cf7("", "", $body);
    } else if ($Return_MessageEmail == 'error') {
        $wpdb->update($wpdb->prefix . 'zibal_contact_form_7', array('status' => 'error'), array('transid' => $wpdb->prepare($Return_Track_Id)), array('%s'), array('%d'));
        //Dispaly
        $body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
        return zgcf7_CreateMessage_cf7("", "", $body);
    }


}

add_shortcode('result_payment', 'zgcf7_result_payment_func');


function zgcf7_CreateMessage_cf7($title, $body, $endstr = "")
{
    if ($endstr != "") {
        return $endstr;
    }
    $tmp = '<div style="border:#CCC 1px solid; width:90%;"> 
    ' . $title . '<br />' . $body . '</div>';
	$tmp = esc_html($tmp);
    return $tmp;
}


function zgcf7_CreatePage_cf7($title, $body)
{
    $tmp = '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>' . $title . '</title>
	</head>'
    . wp_enqueue_style("style", plugins_url('style.css', __FILE__), null, null) .
	'<body class="vipbody">	
	<div class="mrbox2" > 
	<h3><span>' . $title . '</span></h3>
	' . $body . '	
	</div>
	</body>
	</html>';
	$tmp = esc_html($tmp);
    return $tmp;
}




function zgcf7_ZIBAL_Contant_Form_7_Gateway_install()
{
	$dir = plugin_dir_path(__FILE__);
	//register_activation_hook($dir . 'gateway_func.php', 'zgcf7_ZIBAL_Contant_Form_7_Gateway_install');
	
	//  plugin functions
	register_activation_hook(__FILE__, "zgcf7_cf7pp_activate");
	register_deactivation_hook(__FILE__, "zgcf7_cf7pp_deactivate");
	register_uninstall_hook(__FILE__, "zgcf7_cf7pp_uninstall");   
}



function zgcf7_cf7pp_activate()
{
	
	global $wpdb;


    $table_name = $wpdb->prefix . "zibal_contact_form_7";
	$table_name = sanitize_title($table_name);
    if ($wpdb->get_var($wpdb->prepare("show tables like '$table_name'")) != $table_name) {
        $sql = $wpdb->prepare("CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			PRIMARY KEY id (id)
		);");

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // write initical options
    $cf7pp_options = array(
        'merchant' => '',
        'return' => '',
        'error_color'=>'#f44336',
        'sucess_color' => '#8BC34A',
    );

    add_option("cf7pp_options", $cf7pp_options);


}


function zgcf7_cf7pp_deactivate()
{
  
    delete_option("cf7pp_options");
    delete_option("cf7pp_my_plugin_notice_shown");

}


function zgcf7_cf7pp_uninstall()
{
}

// display activation notice
add_action('admin_notices', 'zgcf7_cf7pp_my_plugin_admin_notices');

function zgcf7_cf7pp_my_plugin_admin_notices() {
    if (!get_option('cf7pp_my_plugin_notice_shown')) {
        echo "<div class='updated'><p><a href='admin.php?page=zgcf7_cf7pp_admin_table'>برای تنظیم اطلاعات درگاه  کلیک کنید</a>.</p></div>";
        update_option("cf7pp_my_plugin_notice_shown", "true");
    }
}



// check to make sure contact form 7 is installed and active
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {

    // add paypal menu under contact form 7 menu
    add_action('admin_menu', 'zgcf7_cf7pp_admin_menu', 20);
    function zgcf7_cf7pp_admin_menu()
    {
        $addnew = add_submenu_page('wpcf7',
            __('تنظیمات زیبال', 'contact-form-7'),
            __('تنظیمات زیبال', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'zgcf7_cf7pp_admin_table',
            'zgcf7_cf7pp_admin_table');

        $addnew = add_submenu_page('wpcf7',
            __('لیست تراکنش ها', 'contact-form-7'),
            __('لیست تراکنش ها', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'zgcf7_cf7pp_admin_list_trans',
            'zgcf7_cf7pp_admin_list_trans');

    }


    // hook into contact form 7 - before send
    add_action('wpcf7_before_send_mail', 'zgcf7_cf7pp_before_send_mail');
    function zgcf7_cf7pp_before_send_mail($cf7)
    {
    }


    // hook into contact form 7 - after send
    add_action('wpcf7_mail_sent', 'zgcf7_cf7pp_after_send_mail');
    function zgcf7_cf7pp_after_send_mail($cf7)
    {
        
        global $wpdb;
        global $postid;
        $postid = $cf7->id();
        

        $enable = get_post_meta($postid, "_cf7pp_enable", true);
        $email = get_post_meta($postid, "_cf7pp_email", true);

        if ($enable == "1") {
            if ($email == "2") {

					include_once ('redirect.php');			
                
                exit;

            }
        }

    } // End Function

    
    // hook into contact form 7 form
    add_action('wpcf7_admin_after_additional_settings', 'zgcf7_cf7pp_admin_after_additional_settings');
    function zgcf7_cf7pp_editor_panels($panels)
    {

        $new_page = array(
            'PricePay' => array(
                'title' => __('اطلاعات پرداخت', 'contact-form-7'),
                'callback' => 'zgcf7_cf7pp_admin_after_additional_settings'
            )
        );

        $panels = array_merge($panels, $new_page);

        return $panels;

    }

    add_filter('wpcf7_editor_panels', 'zgcf7_cf7pp_editor_panels');


    function zgcf7_cf7pp_admin_after_additional_settings($cf7)
    {

        $post_id = sanitize_text_field($_GET['post']);
        $enable = get_post_meta($post_id, "_cf7pp_enable", true);
        $price = get_post_meta($post_id, "_cf7pp_price", true);
        $email = get_post_meta($post_id, "_cf7pp_email", true);
        $user_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
        $description = get_post_meta($post_id, "_cf7pp_description", true);

        if ($enable == "1") {
            $checked = "CHECKED";
        } else {
            $checked = "";
        }

        if ($email == "1") {
            $before = "SELECTED";
            $after = "";
        } elseif ($email == "2") {
            $after = "SELECTED";
            $before = "";
        } else {
            $before = "";
            $after = "";
        }

        $admin_table_output = "";
        $admin_table_output .= "<form>";
        $admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
        $admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>اطلاعات پرداخت برای فرم</span></h3>";
        $admin_table_output .= "<div class='inside'>";

        $admin_table_output .= "<div class='mail-field'>";
        $admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
        $admin_table_output .= "<label for='cf71'>فعال سازی امکان پرداخت آنلاین</label>";
        $admin_table_output .= "</div>";

        //input -name
        $admin_table_output .= "<table>";
        $admin_table_output .= "<tr><td>مبلغ: </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$price'></td><td>(مبلغ به ریال)</td></tr>";

        $admin_table_output .= "</table>";


        //input -id
        $admin_table_output .= "<br> برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده نمایید ";
        $admin_table_output .= "<br />
        <span style='color:#F00;'>
        user_email نام فیلد دریافت ایمیل کاربر بایستی user_email انتخاب شود.
        <br />
         description نام فیلد  توضیحات پرداخت بایستی description انتخاب شود.
        <br />
         user_mobile نام فیلد  موبایل بایستی user_mobile انتخاب شود.
        <br />
        user_price اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام user_price ایجاد نمایید
		<br/>
		مانند [text* user_price]
        </span>	";
        $admin_table_output .= "<input type='hidden' name='email' value='2'>";

        $admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";

        $admin_table_output .= "</td></tr></table></form>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        echo $admin_table_output;

    }


    // hook into contact form 7 admin form save
    add_action('wpcf7_save_contact_form', 'zgcf7_cf7pp_save_contact_form');
    function zgcf7_cf7pp_save_contact_form($cf7)
    {

        $post_id = sanitize_text_field($_POST['post']);

        if (!empty($_POST['enable'])) {
            $enable = sanitize_text_field($_POST['enable']);
            update_post_meta($post_id, "_cf7pp_enable", $enable);
        } else {
            update_post_meta($post_id, "_cf7pp_enable", 0);
        }

        /*$name = sanitize_text_field($_POST['name']);
        update_post_meta($post_id, "_cf7pp_name", $name);
        */
        $price = sanitize_text_field($_POST['price']);
        update_post_meta($post_id, "_cf7pp_price", $price);

        /*$id = sanitize_text_field($_POST['id']);
        update_post_meta($post_id, "_cf7pp_id", $id);
        */
        $email = sanitize_text_field($_POST['email']);
        // $email is a string of either 1 or 2 and not actually an email so we had to use sanitize_text_field
        update_post_meta($post_id, "_cf7pp_email", $email);

    }


    function zgcf7_cf7pp_admin_list_trans()
    {
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        global $wpdb;

        $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
        $limit = 6;
        $offset = ($pagenum - 1) * $limit;
        $table_name = $wpdb->prefix . "zibal_contact_form_7";

        $transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name where (status NOT like 'none') ORDER BY $table_name.id DESC LIMIT $offset, $limit", ARRAY_A));
        $transactions = (array)$transactions;
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none') "));
        $num_of_pages = ceil($total / $limit);
        $cntx = 0;

        echo '<div class="wrap">
		<h2>تراکنش فرم ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
                    <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
                    <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</tfoot>
			<tbody>';


        if (count($transactions) == 0) {

            echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="6">هيج تراکنش وجود ندارد.</td>
				</tr>';

        } else {
            foreach ($transactions as $transaction) {
                $transaction = (array)$transaction;

                echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="">' . get_the_title($transaction['idform']) . '</td>';
                echo '<td class="">' . strftime("%a, %B %e, %Y %r", $transaction['created_at']);
                echo '<br />(';
                echo zgcf7_ZIBAL_CF7_relative_time($transaction["created_at"]);
                echo ' قبل)</td>';

                echo '<td class="">' . $transaction['email'] . '</td>';
                echo '<td class="">' . $transaction['user_mobile'] . '</td>';
                echo '<td class="">' . $transaction['cost'] . ' ریال</td>';
                echo '<td class="">';

                if ($transaction['status'] == "success") {
                    echo '<b style="color:#0C9F55">موفقیت آمیز</b>';
                } else {
                    echo '<b style="color:#f00">انجام نشده</b>';
                }
                echo '</td></tr>';

            }
        }
        echo '</tbody>
		</table>
        <br>';


        $page_links = paginate_links(array(
            'base' => add_query_arg('pagenum', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;', 'aag'),
            'next_text' => __('&raquo;', 'aag'),
            'total' => $num_of_pages,
            'current' => $pagenum
        ));

        if ($page_links) {
            echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
        }

        echo '<br>
		<hr>
	</div>';
    }


    function zgcf7_cf7pp_admin_table()
    {
        global $wpdb;
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        echo '<form method="post" action=' . $_SERVER["REQUEST_URI"] . ' enctype="multipart/form-data">';

        // save and update options
        if (isset($_POST['update'])) {
		
            $options['gateway_merchantid'] = sanitize_text_field($_POST['gateway_merchantid']);
            $options['return'] = sanitize_text_field($_POST['return']);
            $options['sucess_color'] = sanitize_hex_color($_POST['sucess_color']);
            $options['error_color'] = sanitize_hex_color($_POST['error_color']);

            update_option("cf7pp_options", $options);

            update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
            update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
            
            echo "<br /><div class='updated'><p><strong>";
            _e("Settings Updated.");
            echo "</strong></p></div>";

        }

        $options = get_option('cf7pp_options');
        foreach ($options as $k => $v) {
            $value[$k] = $v;
        }
        

        $theme_message = get_option('cf7pp_theme_message', '');
        $theme_error_message = get_option('cf7pp_theme_error_message', '');
        
        echo "<div class='wrap'><h2>Contact Form 7 - Gateway Settings</h2></div><br />
		<table width='90%'><tr><td>";

        echo '<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; پرداخت آنلاین برای فرم های Contact Form 7
		</div><div style="background-color:#fff;border: 1px solid #E5E5E5;padding:5px;"><br />
		
		
		<q1 style="color:#09F;">با استفاده از این قسمت میتوانید اطلاعات مربوط به درگاه  خود را تکمیل نمایید 
    <br>
    در بخش ایجاد فرم جدید می توانید براساس نام فیلد های زیر فرم را برای اتصال به درگاه پرداخت آماده کنید
    <br>
    user_email : برای دریافت ایمیل کاربر   
    <br>
    description : برای در یافت توضیحات خرید استفاده شود و الزامی شود  
    <br>
    user_mobile : برای دریافت موبایل کاربر   
    <br>
    user_price : جهت دریافت مبلغ از کاربر
    <br>
 برای نمونه : [text user_price]
 <br>
   برای مهم واجباری کردن* قرار دهید : [text* user_price]
    </q1>
<br/><br/><br/>
    <q1 style="color:#60F;">
    لینک بازگشت از تراکنش بایستی به یکی از برگه های سایت باشد 
    <br>
    در این برگه بایستی از شورت کد زیر استفاده شود
    <br>
    [result_payment]   
    <br>
<br/><br/><br/>
حتما برررسی نمایید کد زیر در فایل wp-config.php وجود داشته باشد. که اگر نبود خودتان اضافه نمایید.
<br>
<pre style="direction: ltr;">define("WPCF7_LOAD_JS",false);</pre>
<br/><br/><br/>

    <q1> 

    

    <q1></q1></q1></q1></q1></b></b></div><b><b>

		
		
		
		<br /><br />
		
		</div><br /><br />
		
		<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; اطلاعات درگاه پرداخت
		</div>
		<div style="background-color:#fff;border: 1px solid #E5E5E5;padding:20px;">
					
		<hr>	
		<table>
         
          <tr>
            <td>کد درگاه پرداخت (مرچنت) زیبال (جهت تست میتوانید از zibal استفاده بفرمایید):</td>';
        echo '<td><input type="text" style="width:450px;text-align:left;direction:ltr;" name="gateway_merchantid" value="' . $value['gateway_merchantid'] . '">الزامی</td>
          </tr>
        </table> 
		 <hr>
        <table> 
          <tr>
            <td>لینک بازگشت از تراکنش :</td>
            <td><input type="text" name="return" style="width:450px;text-align:left;direction:ltr;" value="' . $value['return'] . '">
            الزامی
            
            <br />
            فقط  عنوان  برگه را قرار دهید مانند  Vpay
             <br />
         حتما باید یک برگه ایجادکنید
 و کد [result_payment]  را در ان قرار دهید 
            <br />
            <br />
            </td>
            <td></td>
          </tr>
		  <tr>
            <td>قالب تراکنش موفق :</td>
            <td>
			<textarea name="theme_message" style="width:450px;text-align:left;direction:ltr;">' . $theme_message . '</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز بودن تراکنش نشان دهید
			<br/>
			<b>از شورتکد [transaction_id] برای نمایش شماره تراکنش در قالب های نمایشی استفاده کنید</b>
            </td>
                  <td></td>

          </tr>
          <tr><td></td></tr>
           <tr>
            <td>قالب تراکنش ناموفق :</td>
            <td>
			<textarea name="theme_error_message" style="width:450px;text-align:left;direction:ltr;">' . $theme_error_message . '</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز نبودن تراکنش نشان دهید
			<br/>

            </td>
            <td></td>
          </tr>
          <tr>
          
           <td>رنگ متن موفقیت آمیز بودن تراکنش :  </td>

            <td>
            <input type="text" name="sucess_color" style="width:150px;text-align:left;direction:ltr;color:'.$value['sucess_color'].'" value="' . $value['sucess_color'] . '">
           
 مانند :     #8BC34A
           </td>
          
          </tr>
          
          <tr>
          
           <td>رنگ متن موفقیت آمیز نبودن تراکنش :  </td>

            <td>
            <input type="text" name="error_color" style="width:150px;text-align:left;direction:ltr;color:'.$value['error_color'].'" value="' . $value['error_color'] . '">
            مانند : #f44336
            </td>
          </tr>
          <tr><td></td></tr><tr><td></td></tr>
		  
		   <tr>
          <td colspan="3">
          <input type="submit" name="btn2" class="button-primary" style="font-size: 17px;line-height: 28px;height: 32px;float: right;" value="ذخیره تنظیمات">
          </td>
          </tr>
        </table>
        
        </div>
        <br /><br />';
        echo "
		<br />		
		<input type='hidden' name='update'>
		</form>		
		</td></tr></table>";

    }
} else {
    // give warning if contact form 7 is not active
    function zgcf7_cf7pp_my_admin_notice()
    {
        echo '<div class="error">
			<p>' . _e('<b> افزونه درگاه بانکی برای افزونه Contact Form 7 :</b> Contact Form 7 باید فعال باشد ', 'my-text-domain') . '</p>
		</div>
		';
    }

    add_action('admin_notices', 'zgcf7_cf7pp_my_admin_notice');
}
?>