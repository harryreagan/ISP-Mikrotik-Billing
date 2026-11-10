<?php

namespace App\Http\Controllers;

use App\Models\MainSiteData;
use App\Models\PackageList;

class MainSiteController extends Controller
{
    public function index()
    {
        $siteData = MainSiteData::getActive();
        $packages = PackageList::orderBy('sort_order')
            ->where('show_on_site', true)
            ->limit(10)
            ->get();

        $reviews = \App\Models\CustomerReview::with(['pppUser.customer'])
            ->where('show_on_site', true)
            ->latest()
            ->get();

        // Record the visitor hit
        try {
            \App\Models\SiteVisitor::create([
                'ip_address' => request()->ip(),
                'visited_date' => now()->toDateString(),
            ]);
        } catch (\Exception $e) {
            // Log warning but prevent app crash on DB errors
            \Log::warning('Visitor tracking failed: ' . $e->getMessage());
        }

        $uniqueVisitors = 2000 + \App\Models\SiteVisitor::distinct('ip_address')->count();
        $totalVisits = 5000 + \App\Models\SiteVisitor::count();

        return view('main-site', compact('siteData', 'packages', 'reviews', 'uniqueVisitors', 'totalVisits'));
    }

    public function allPackages()
    {
        $siteData = MainSiteData::getActive();

        // Seed default packages if none exist yet
        if (PackageList::count() === 0) {
            PackageList::create([
                'package'             => '1 Hour Hotspot',
                'type'                => 'hotspot',
                'price'               => 20.00,
                'speed'               => '10 Mbps',
                'speed_download'      => 10,
                'speed_download_unit' => 'Mbps',
                'speed_upload'        => 5,
                'speed_upload_unit'   => 'Mbps',
                'validity_value'      => 1,
                'validity_unit'       => 'hours',
                'shared_users'        => 1,
                'show_on_site'        => true,
                'show_public'         => true,
                'is_active'           => true,
                'description'         => 'High-speed internet for quick browsing and downloads.',
                'sort_order'          => 1,
            ]);
            PackageList::create([
                'package'             => '24 Hours Unlimited',
                'type'                => 'hotspot',
                'price'               => 50.00,
                'speed'               => '10 Mbps',
                'speed_download'      => 10,
                'speed_download_unit' => 'Mbps',
                'speed_upload'        => 5,
                'speed_upload_unit'   => 'Mbps',
                'validity_value'      => 24,
                'validity_unit'       => 'hours',
                'shared_users'        => 1,
                'show_on_site'        => true,
                'show_public'         => true,
                'is_active'           => true,
                'description'         => 'All-day unlimited connectivity for streaming and work.',
                'sort_order'          => 2,
            ]);
            PackageList::create([
                'package'             => '30 Days Home Fiber',
                'type'                => 'pppoe',
                'price'               => 1500.00,
                'speed'               => '15 Mbps',
                'speed_download'      => 15,
                'speed_download_unit' => 'Mbps',
                'speed_upload'        => 15,
                'speed_upload_unit'   => 'Mbps',
                'validity_value'      => 30,
                'validity_unit'       => 'days',
                'shared_users'        => 1,
                'show_on_site'        => true,
                'show_public'         => true,
                'is_active'           => true,
                'description'         => 'Monthly fiber internet for homes and offices.',
                'sort_order'          => 3,
            ]);
        }

        $packages = PackageList::publiclyVisible()
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        // Record the visitor hit
        try {
            \App\Models\SiteVisitor::create([
                'ip_address' => request()->ip(),
                'visited_date' => now()->toDateString(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Visitor tracking failed in allPackages: ' . $e->getMessage());
        }

        $uniqueVisitors = 2000 + \App\Models\SiteVisitor::distinct('ip_address')->count();
        $totalVisits = 5000 + \App\Models\SiteVisitor::count();

        return view('all-packages', compact('siteData', 'packages', 'uniqueVisitors', 'totalVisits'));
    }
}
