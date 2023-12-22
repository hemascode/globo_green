<?php

namespace App\Http\Controllers\WEB\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\City;
use App\Models\Vendor;
use App\Models\VendorSocialLink;
use App\Models\SellerWithdraw;
use App\Models\SellerMailLog;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Models\BannerImage;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class SellerProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function index(){
        $user = Auth::guard('web')->user();

        $seller = Vendor::with('user','socialLinks','products')->where('user_id', $user->id)->first();
        $countries = Country::orderBy('name','asc')->where('status',1)->get();
        $states = CountryState::orderBy('name','asc')->where(['status' => 1, 'country_id' => $user->country_id])->get();
        $cities = City::orderBy('name','asc')->where(['status' => 1, 'country_state_id' => $user->state_id])->get();
        $totalWithdraw = SellerWithdraw::where('seller_id',$seller->id)->where('status',1)->sum('total_amount');
        $totalPendingWithdraw = SellerWithdraw::where('seller_id',$seller->id)->where('status',0)->sum('withdraw_amount');

        $totalAmount = 0;
        $totalSoldProduct = 0;
        $orderProducts = OrderProduct::with('order')->where('seller_id', $seller->id)->get();
        foreach($orderProducts as $orderProduct){
            if($orderProduct->order&& $orderProduct->payment_status == 1 && $orderProduct->order->order_status == 3){
                $price = ($orderProduct->unit_price * $orderProduct->qty) + $orderProduct->vat;
                $totalAmount = $totalAmount + $price;
                $totalSoldProduct = $totalSoldProduct + $orderProduct->qty;
            }
        }

        $defaultProfile = BannerImage::whereId('15')->first();
        $setting = Setting::first();

        return view('seller.seller_profile', compact('user','countries','states','cities','seller','totalWithdraw','totalAmount','totalPendingWithdraw','totalSoldProduct','setting','defaultProfile'));
    }

    public function changePassword(){
        $user = Auth::guard('web')->user();
        $setting = Setting::first();
        return view('seller.change_password', compact('user','setting'));
    }

    public function stateByCountry($id){
        $states = CountryState::where(['status' => 1, 'country_id' => $id])->get();
        return response()->json(['states'=>$states]);
    }

    public function cityByState($id){
        $cities = City::where(['status' => 1, 'country_state_id' => $id])->get();
        return response()->json(['cities'=>$cities]);
    }

    public function updateSellerProfile(Request $request)
{
    $user = Auth::guard('web')->user();

    $request->validate([
        'name' => 'required',
        'email' => 'required|unique:users,email,' . $user->id,
        'phone' => 'required',
        'address' => 'required',
        'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Add image validation
    ], [
        // Custom validation messages here...
    ]);

    $user->name = $request->name;
    $user->phone = $request->phone;
    $user->address = $request->address;

    // Save user data
    $user->save();

    // Handle image upload
    if ($request->hasFile('image')) {
        $oldImage = $user->image;

        $image = $request->file('image');
        $imageName = Str::slug($request->name) . '-' . now()->format('Y-m-d-h-i-s') . '-' . rand(999, 9999) . '.' . $image->getClientOriginalExtension();

        $image->storeAs('uploads/custom-images', $imageName, 'public');

        $user->image = 'uploads/custom-images/' . $imageName;

        // Delete old image if it exists
        if ($oldImage && File::exists(public_path($oldImage))) {
            File::delete(public_path($oldImage));
        }
    }

    // Display success message and redirect back
    return redirect()->back()->with([
        'message' => trans('admin_validation.Update Successfully'),
        'alert-type' => 'success',
    ]);
}


    public function updatePassword(Request $request){
        $user = Auth::guard('web')->user();
        $rules = [
            'password'=>'required|min:4|confirmed',
        ];

        $customMessages = [
            'password.required' => trans('admin_validation.Password is required'),
            'password.min' => trans('admin_validation.Password must be 4 characters'),
            'password.confirmed' => trans('admin_validation.Confirm password does not match'),
        ];
        $this->validate($request, $rules,$customMessages);

        $user->password = Hash::make($request->password);
        $user->save();
        $notification= trans('admin_validation.Password Change Successfully');
        $notification=array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);
    }

    public function myShop(){
        $user = Auth::guard('web')->user();
        $seller = Vendor::with('socialLinks')->where('user_id',$user->id)->first();

        return view('seller.shop_profile', compact('user','seller'));
    }

    public function updateSellerSop(Request $request){

        $user = Auth::guard('web')->user();
        $seller = Vendor::where('user_id',$user->id)->first();
        $rules = [
            'shop_name'=>'required|unique:vendors,email,'.$seller->id,
            'email'=>'required|unique:vendors,email,'.$seller->id,
            'phone'=>'required',
            'opens_at'=>'required',
            'closed_at'=>'required',
            'address'=>'required',
            'greeting_msg'=>'required',
        ];
        $customMessages = [
            'shop_name.required' => trans('admin_validation.Shop name is required'),
            'shop_name.unique' => trans('admin_validation.Shop anme is required'),
            'email.required' => trans('admin_validation.Email is required'),
            'email.unique' => trans('admin_validation.Email already exist'),
            'phone.required' => trans('admin_validation.Phone is required'),
            'greeting_msg.required' => trans('admin_validation.Greeting Messsage is required'),
            'opens_at.required' => trans('admin_validation.Opens at is required'),
            'closed_at.required' => trans('admin_validation.Close at is required'),
            'address.required' => trans('admin_validation.Address is required'),
        ];
        $this->validate($request, $rules,$customMessages);

        $seller->phone = $request->phone;
        $seller->open_at = $request->opens_at;
        $seller->closed_at = $request->closed_at;
        $seller->address = $request->address;
        $seller->greeting_msg = $request->greeting_msg;
        $seller->seo_title = $request->seo_title ? $request->seo_title : $request->shop_name;
        $seller->seo_description = $request->seo_description ? $request->seo_description : $request->shop_name;
        $seller->save();

        if($request->logo){
            $exist_banner = $seller->logo;
            $extention = $request->logo->getClientOriginalExtension();
            $banner_name = 'seller-banner'.date('-Y-m-d-h-i-s-').rand(999,9999).'.'.$extention;
            $banner_name = 'uploads/custom-images/'.$banner_name;
            Image::make($request->logo)
                ->save(public_path().'/'.$banner_name);
            $seller->logo = $banner_name;
            $seller->save();
            if($exist_banner){
                if(File::exists(public_path().'/'.$exist_banner))unlink(public_path().'/'.$exist_banner);
            }
        }


        if($request->banner_image){
            $exist_banner = $seller->banner_image;
            $extention = $request->banner_image->getClientOriginalExtension();
            $banner_name = 'seller-banner'.date('-Y-m-d-h-i-s-').rand(999,9999).'.'.$extention;
            $banner_name = 'uploads/custom-images/'.$banner_name;
            Image::make($request->banner_image)
                ->save(public_path().'/'.$banner_name);
            $seller->banner_image = $banner_name;
            $seller->save();
            if($exist_banner){
                if(File::exists(public_path().'/'.$exist_banner))unlink(public_path().'/'.$exist_banner);
            }
        }

        if(count($request->links) > 0){
            $socialLinks = $seller->socialLinks;
            foreach($socialLinks as $link){
                $link->delete();
            }
            foreach($request->links as $index=> $link){
                if($request->links[$index] != null && $request->icons[$index] != null){
                    $socialLink = new VendorSocialLink();
                    $socialLink->vendor_id = $seller->id;
                    $socialLink->icon=$request->icons[$index];
                    $socialLink->link=$request->links[$index];
                    $socialLink->save();
                }
            }
        }

        $notification= trans('admin_validation.Update Successfully');
        $notification=array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);
    }

    public function removeSellerSocialLink($id){
        $socialLink = VendorSocialLink::find($id);
        $socialLink->delete();
        return response()->json(['success' => trans('admin_validation.Delete Successfully')]);
    }

    public function emailHistory(){
        $user = Auth::guard('web')->user();
        $seller = $user->seller;
        $emails = SellerMailLog::where('seller_id',$seller->id)->orderBy('id','desc')->get();

        return response()->json(['emails' => $emails, 'user' => $user]);

    }
}
