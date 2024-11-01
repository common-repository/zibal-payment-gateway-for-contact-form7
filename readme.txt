=== Zibal Payment Gateway for Contact Form 7 ===
Contributors: yahyakangi
Tags: contact form 7,zibal,gateway,payment,زیبال
Requires at least: 4.5
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

با نصب این پلاگین می توانید از خدمات درگاه پرداخت زیبال برروی افزونه فرم تماس ۷ استفاده کنید!

== Description ==
 افزونه Zibal Payment Gateway for Contact Form 7 امکان فروش اینترنتی و آنلاین از طریق درگاه پرداخت زیبال به فرم های Contact Form 7 اضافه می کند. 
افزونه Zibal Payment Gateway for Contact Form 7 امکان ایجاد پرداخت و همچنین مشاهده پرداخت‌های انجام شده را به افزونه فرم تماس ۷ اضافه می‌کند.


== Installation ==
برای فعال سازی درگاه پرداخت زیبال برای فرم تماس ۷ مراحل زیر را دنبال کنید:

1. در سایت https://zibal.ir ثبت نام کنید و درگاه ایجاد کنید.
2. پلاگین را نصب و فعال کنید.
3. از منو افزونه فرم تماس ۷ به منو تنظیمات افزونه پرداخت زیبال مراجعه کنید.
4. درگاه زیبال را فعال و در قسمت مرچنت مقدار merchant درگاه خود را وارد کنید.
5. در صورت نیاز به تست درگاه مقدار مرچنت را zibal قرار دهید.

حتما برررسی نمایید کد زیر در فایل wp-config.php وجود داشته باشد. که اگر نبود خودتان اضافه نمایید.
define("WPCF7_LOAD_JS",false);
