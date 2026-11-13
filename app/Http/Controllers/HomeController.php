<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Contacts;
use App\Models\Subscribers;
use App\Models\BusinessPlan;
use App\Models\BusinessPlanFiles;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class HomeController extends Controller
{
    public function index()
    {
        return view('frontend.home');
    }

    public function coffeeSourcing()
    {
        return view('frontend.coffee-sourcing');
    }

    public function coffeeProcessing()
    {
        return view('frontend.coffee-processing');
    }
    
    public function trustedPartners()
    {
        return view('frontend.trusted-partners');
    }

    public function products()
    {
        return view('frontend.products');
    }

    public function productDetails()
    {
        return view('frontend.product-details');
    }
    


    public function contactSave(Request $request)
    {
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string',
        ]);

        Contacts::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'message' => $request->message,
        ]);
            $this->sendEmail(
                "AMELAUG New Contact",
                "You received a new contact request.<br><strong>Name:</strong> {$request->name}<br><strong>Email:</strong> {$request->email}<br><strong>Phone Number:</strong> {$request->phone}<br><strong>Message:</strong>{$request->message}
                <br><br> Please log in and review your CMS at your earliest convenience."
            );
        return response()->json(['message' => 'Thank you for contacting us. '], 200);

    }

    public function sendEmail($subject,$message){

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'noreply@amelaug.com';
            $mail->Password   = '0A~IFnUm?kEm'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            $mail->Port       = 465; // SMTP Port

            $mail->setFrom('noreply@amelaug.com', 'No Reply');
            $mail->addAddress('info@amelaug.com'); 
            $mail->addAddress('mt@arzgt.com'); 
            

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return "1";
        } catch (Exception $e) {
            return "-1";
        }
    }

     public function subscribe(Request $request)
    {
        $validateData = $request->validate([
            'email' => 'required|email|max:255',
        ]);
    
        Subscription::create([
            'email' => $validateData['email'],
        ]);

        $this->sendEmail("AMELAUG New Subscription", "Please log in and review your CMS at your earliest convenience.");
    
        return response()->json(['message' => 'Thank you for your subscription'], 200);
    }

}
