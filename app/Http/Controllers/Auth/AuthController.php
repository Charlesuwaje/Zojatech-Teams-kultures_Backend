<?php

namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\codeCheckRequest;
use App\Http\Requests\forgetPasswordReques;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\passwordResetRequest;
use App\Http\Traits\ResponseTrait;
use App\Mail\SendCodeResetPassword;
use App\Models\Crud;
use App\Models\File;
use App\Models\Music;
use App\Models\ResestCodePassword;
use App\Models\Status;
use App\Models\user;
use App\Models\users;
use App\Models\UserVerify;
use App\Notifications\EmailNotification;
use App\Traits\HttpResponses;
use GrahamCampbell\ResultType\Success;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Unicodeveloper\Paystack\Facades\Paystack;

class AuthController extends Controller
{
    // Register
        use HttpResponses;
        use ResponseTrait;
    //  register
    public function register(request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'username' => 'required|string|unique:users',
            'email' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            ]);

        $user = new users([
             'firstname' => $request->firstname,
             'lastname' => $request->lastname,
             'email' => $request->email,
             'username' => $request->username,
             'password' => Hash::make($request->password),
         ]);
        $user->save();
        // $token = rand(000667, 999);
        // userverify::create([
        //     'user_id' => $user->id,
        //     'token' => $token,

        // ]);
        // Mail::send('email.EmailVerification', ['token' => $token], function ($m) use ($user) {
        //     $m->from('kulture@gmail.com', 'kulture');
        //     $m->to($user->email);
        //     $m->subject('Email verification mail');
        // });
       
        // $user->notify(new EmailNotification($token));
       
        $token = $user->createToken('authtoken');
        return $this->success([
            'user' => $user,
            'token' => $user->createToken('API Token of')->plainTextToken,
        ], 'Register Successful');
   
    }

    // Login

          public function login(LoginUserRequest $request)
          {
              $request->validated($request->only(['email', 'password']));

              if (!Auth::attempt($request->only(['email', 'password']))) {
                return $this->error('', 'Credentials do not match', 401);
              }

              $user = users::where('email', $request->email)->first();

              return $this->successResponse('Login successful', [
                  'user' => $user,
                  'access_token' => $user->createToken('API Token')->plainTextToken,
              ]);
          }

      // forgetpassword
      public function forgetPassword(forgetPasswordReques $request)
      {
          // Delete all old code that user send before.
          ResestCodePassword::where('email', $request->email)->delete();

          // Generate random code

          $data['email'] = $request->email;
          $data['Token'] = rand(000, 999);
          $data['created'] = now();

          // Create a new code
          $codeData = ResestCodePassword::create($data);

          // Send email to user

          Mail::to($request->email)->send(new SendCodeResetPassword($data['Token']));

          return response(['message' => trans('passwords.sent')], 200);
      }

      // code check
      public function codeCheck(Request $request)
      {
          $request->validate([
              'Token' => 'required|string|exists:resetcodepasswords',
          ]);

          // find the code
          $passwordReset = ResestCodePassword::firstWhere('Token', $request->token);

          // check if it does not expired: the time is one hour
          if ($passwordReset->created_at > now()->addHour(2)) {
              $passwordReset->delete();

              return response(['message' => trans('passwords.code_is_expire')], 422);
          }

          return response([
              'Token' => $passwordReset->token,
              'message' => trans('passwords.code_is_valid'),
          ], 200);
      }

          // passwordReset
          public function passwordReset(passwordResetRequest $request)
          {
              $user = users::where('email', $request->email)->first();

              if (!$user) {
                  return 'This user is not found';
              }
              $user->fill([
                  'password' => Hash::make($request->password),
              ]);
              $user->save();
              DB::table('resetcodepassword')->
              where('email', $user->email)->
              delete();

              return response()->json([
                'message' => 'Password reset successful',
                 'data' => [$user],
              ]);

              //  PasswordResetOpt
          }

 public function PasswordResetOpt(Request $request)
 {
     if (empty($request->token)) {
         return $this->badRequestResponse('Error');
     }
     $check = ResestCodePassword::where('token', $request->token)->first();
     if (is_null($check)) {
         return $this->badRequestResponse('Error invalid token');
     }

     return $this->successResponse('done', $check);
 }

          // logout
      public function logout(Request $request)
      {
          Auth::logout();
        // Auth::user()->currentAccessToken()->delete(); 

          return response()->json(['message' => 'Successfully logged out']);
        // return $this->success([
        //     'message'=>'you have successfully logged out and your token has been deleted '
        // ]);
      }
    
      // search code.

      public function search(Request $request)
      {
          $search = DB::table('users','music','files');
          if ($request->query('keyword')) {
              $search = $search->where( 'like',  'title', '%'.$request->query('keyword'. '%'))->paginate('10');
          }
          if ($request->query('afro_beat') == 'afrobeat') {
              $search = $search->where('name', $request->query('afro'));
          }
          if ($request->query('world') == 'world') {
              $search = $search->where('name', $request->query('world'));
          }
          if ($request->query('juju') == 'juju') {
              $search = $search->where('name', $request->query('juju'));
          }
          $search = $search->get();

          return response()->json([
              'message' => 'search successful',
                  'data' => [
                      'search' => $search,
                  ],
          ], 200);
      }
      
// filter
      public function filter(Request $request)
      {
          try {
              //code...
              $filter = DB::table('music')
                  ->when($request->afro_beat, function ($q, $afro_beat) {
                      return $q->where('afro_beat', '=', $afro_beat);
                  })
                  ->when($request->world, function ($q, $world) {
                      return $q->where('world', '=', $world);
                  })
                  ->when($request->juju, function ($q, $juju) {
                      return $q->where('juju', '=', $juju);
                  })
                  ->orderByDesc('music.id')
                  ->get();
          } catch (\Throwable $th) {
              //throw $th;
              return $this->badRequestResponse("Error", ['errror' => $th->getMessage()]);
          }
          return $this->successResponse('done', $filter);
      }
      // fileUpload Code

      public function fileupload(Request $request)
      {
          $file = new File();

          $file->files = $request->file('file')->store('apiFile');
          $result = $file->save();
          if ($result) {
              return ['result' => 'file uploaded sucessfully'];
          } else {
              return ['result' => 'file upload failed'];
          }
      }

          //  payStack Payment gateway code
        //   public function redirectToGateway(Request $request)
        //   {
        //       try {
        //           $ref = Paystack::genTranxRef();
        //           // dd($request->$ref);


        //           $request['reference'] = $ref;
        //           $request['email'] = "charlesuwaje02@gmail.com";
        //           $request['amount'] = $request->amount * 100;

        //           return Paystack::getAuthorizationUrl($request->all())->redirectNow();
        //       } catch (\Exception $e) {
        //         return response()->json([
        //         'status'=>false,
        //         'message'=>$e->getmessage()],500);
        //       }
        //   }
      
      
      
                 // payment gate-way
        // public function redirectToGateway(Request $request)
        // {
        //     try {
        //         $ref = Paystack::genTranxRef();
        //         $request['reference'] = $ref;
        //         $request['email'] = $request->email;
        //         $request['amount'] = $request->amount * 100;
        //         $request['callback_url'] = route('payment.callback'); // Set the callback URL for Paystack to redirect after payment
                
        //         $headers = [
        //             "Authorization: Bearer " .env("PAYSTACK_SECRET_KEY"),
        //             "Content-Type: application/json"
                    
        //         ];
        //         // return $headers;
        //         $url = "https://api.paystack.co/transaction/initialize";
        //         $ch = curl_init();
        //         curl_setopt_array(
        //             $ch,
        //             array(
        //                 CURLOPT_URL => $url,
        //                 CURLOPT_RETURNTRANSFER => true,
        //                 CURLOPT_ENCODING => '',
        //                 CURLOPT_MAXREDIRS => 10,
        //                 CURLOPT_TIMEOUT => 0,
        //                 CURLOPT_SSL_VERIFYPEER => false,
        //                 CURLOPT_HEADER => false,
        //                 CURLOPT_REFERER => $url,
        //                 CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.86 Safari/537.36",
        //                 CURLOPT_FOLLOWLOCATION => true,
        //                 CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //                 CURLOPT_CUSTOMREQUEST => 'POST',
        //                 CURLOPT_POSTFIELDS => json_encode($request->all()),
        //                 CURLOPT_HTTPHEADER => $headers
        //             ),
        //         );
    
        //          $response = curl_exec($ch);
    
        //         curl_close($ch);
    
        //         $result = json_decode($response, true);
    
        //         if ($response) {
        //             return $result;
        //         } else {
        //             return response([
        //                'status' => curl_error($ch),
        //              ], 404);
        //          }
        //      } catch (\Exception $e) {
        //          return response()->json([
        //              'status' => false,
        //              'message' => $e->getMessage()
        //          ], 500);
        //     }
        //  }
        public function redirectToGateway(Request $request)
    {
        try {
           $this->validate($request, [
            'amount' => 'required|numeric',
        ]);

        $url = "https://api.paystack.co/transaction/initialize";
        $amount = $request->amount;
        $data = [
            "amount" => $amount * 100,
            "email" =>  $request->email,
            "callback_url" => route('payment.callback'), //        Set the callback URL for Paystack to redirect after payment
            "metadata" => [
                'amount' => $amount
            ],
        ];
         $headers = [
                "Authorization: Bearer " . env("PAYSTACK_SECRET_KEY"),
                "Content-Type: application/json"
            ];

            $url = "https://api.paystack.co/transaction/initialize";
            $ch = curl_init();
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_REFERER => $url,
                    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.86 Safari/537.36",
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => $headers
                ),
            );

            $response = curl_exec($ch);

            curl_close($ch);

            $result = json_decode($response, true);

            if ($response) {
                return $result;
            } else {
                return response([
                    'status' => curl_error($ch),
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
          /**
           * Obtain Paystack payment information.
           *
           * @return void
           */
        //   public function handleGatewayCallback()
        //   {
        //       $paymentDetails = Paystack::getPaymentData();

        //       return Paystack::getAuthorizationUrl($paymentDetails)->redirectNow();
        //   }
        public function handleGatewayCallback()
        {
            $data = request();
            // return $data;
            // dd($data);
            $reference = $data->reference;
    
            // Set up the Paystack API URL
            $url = "https://api.paystack.co/transaction/verify/$reference";
            // Set up the authorization headers
            $headers = [
                "Authorization: Bearer " . env("PAYSTACK_SECRET_KEY"),
                "Content-Type: application/json"
            ];
    
            // Set up the curl request
    //  return $headers;

            $ch = curl_init();
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_REFERER => $url,
                    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.86 Safari/537.36",
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => $headers
                ),
            );
    
            // Send the curl request and get the response
            $response = curl_exec($ch);
            // return $response;
            
            curl_close($ch);
    
            $result = json_decode($response, true);
            // return $result;
    
            // Check the payment verification result
            if (isset($result['status']) && $result['status'] === true) {
                $amount = $result['data']['metadata']['amount'];
                  return Paystack::getAuthorizationUrl($amount)->redirectNow();
                // return PayStack::getAuthorizationUrl($result['data']['metadata']['amount'])->redirectNow();
                // return "sucessfully";
            } else     
                return 'Payment verification failed';
            }
         
         
            public function create(Request $request){
            $request->validate([
                'country'=>'required|string',
                   'state'=>'required|string',
                    'facebook'=>'required|string',
                   'instagram'=> 'required|string',
                  'linkdin'=> 'required|string',
                ]);    
                $user=new Crud([
               
                 
                    'country'=>$request->country,
                    'state'=>$request->state,
                    'facebook' =>$request->facebook,
                    'instagram' =>$request->instagram,
                    'linkdin'   =>$request->linkdin,
                  
                    
                ]);
              
                $user->save();
                return response()->json([
                    'message'=>'created  successful',
                        'data'=>[
                            "user"=>$user
                        ]
                ],200);
                     }

       public function update(Request $request, $id)
       {
           try {
               $findCrud = Crud::findorfail($id);
               $findCrud->country = $request->country;
               $findCrud->state = $request->state;
               $findCrud->facebook = $request->facebook;
               $findCrud->instagram = $request->insagram;
               $findCrud->linkdin = $request->linkdin;
               $findCrud->save();
           } catch (\Exception $th) {
               // throw $th;
               return response()->json(['message' => 'updated sucessfully'], 200);
           }
        //   email verification code.

       }
       
         public function verify(EmailVerificationRequest $request)
          {
            //   if ($request->users()->hasVerifiedEmail()) {
            //       return [
            //           'message' => 'Email already verified',
            //       ];
            //   }

            //   if ($request->users()->markEmailAsVerified()) {
            //       event(new Verified($request->user()));
            //   }

            //   return [
            //       'message' => 'Email has been verified',
            //   ];
          }
          public function verifyToken(Request $request)
          {
              if (empty($request->token)) {
                return $this->error('', 'Credentials do not match invalid Token', 401);

              }
                 $check = users::where('token', $request->token)->first();
              if (is_Null($check)) {
                return $this->error('', 'Credentials do not match invalid  Token', 401);

              }
              $user = users::where('email', $check->user->email);
              if (is_null($check->user->email_verified_at)) {
                  $user->update([
                      'email_verified_at' => NOW(),
                  ]);
              }
              $token = $user->first()->createToken('myapp')->plainTextToken;
              return $this->success('sucessfully verifield', $token);
          }
         
         
                  public function status(Request $request, $id)
          {
              try {
      
                 $statusid = Status::findorfail($id);
                 
                 $statusid ->name = $request->name;
                  $statusid ->save();
              } catch (\Throwable $th) {
                  //throw $th;
                  return $this->badRequestResponse('Error', ['error' => $th->getMessage()]);
              }
            //   return $this->sucess("updated sucessfully",$statusid );
              return $this->createdResponse("updated sucessfully", $statusid);
          }
}
 