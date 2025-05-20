<?php

namespace App\Http\Controllers;

use App\Models\ShopSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ShopSettingsController extends Controller
{
    public function index()
    {
        $settings = ShopSettings::first() ?? new ShopSettings([
            'shop_name' => '',
            'shop_address' => '',
            'shop_phone' => '',
            'shop_email' => '',
            'currency' => 'MYR',
            'tax_percentage' => 0,
            'logo_path' => null,
            'company_number' => null,
            'tax_number' => null,
            'payment_details' => null,
            'footer_text' => null,
        ]);
        
        return Inertia::render('Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'shop_name' => 'required|string|max:255',
            'shop_address' => 'required|string|max:500',
            'shop_phone' => 'required|string|max:20',
            'shop_email' => 'required|email|max:255',
            'currency' => 'required|string|size:3|in:MYR,SGD,USD',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'company_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'payment_details' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $settings = ShopSettings::first();
        if (!$settings) {
            $settings = new ShopSettings();
        }

        // Handle logo upload if present
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            
            // Store new logo
            $path = $request->file('logo')->store('logos', 'public');
            $settings->logo_path = $path;
        }

        $settings->fill($request->except('logo'));
        $settings->save();

        return redirect()->back()->with('success', 'Shop settings updated successfully.');
    }
} 