@extends('layouts.admin')

@section('title', 'Visual Moving Fleet Radar & 100m Geofences')
@section('header_title', 'Live Fleet Radar & 100m Geofence Telemetry')
@section('header_subtitle', 'Real-Time moving bike markers, shop locations, battery levels and 100m geofences')

@section('content')
<div class="space-y-6" x-data="fleetRadarMap()">

    <!-- Map Control Header Card -->
    <div class="glass-card rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                <span class="w-2 h-2 mr-2 rounded-full bg-emerald-500 pulse-emerald"></span>
                RADAR ONLINE • 10s PING
            </span>
            <span class="text-xs font-bold text-slate-500">Rohtak Urban Zone</span>
        </div>
        <div class="flex items-center space-x-4 text-xs font-semibold text-slate-600">
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-indigo-600 inline-block"></span>
                <span>Moving Collector Bikes</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                <span>Retailer Shops</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-emerald-200 border border-emerald-500 inline-block"></span>
                <span>100m Geofence Zone</span>
            </div>
        </div>
    </div>

    <!-- Map Canvas Container -->
    <div class="glass-card rounded-2xl overflow-hidden relative" style="height: 620px;">
        <div id="radarMap" class="w-full h-full z-10"></div>
    </div>

</div>

<script>
    function fleetRadarMap() {
        return {
            map: null,
            collectorMarkers: {},
            retailers: @json($retailers),
            collectors: @json($collectors),

            init() {
                // Initialize Leaflet map centered on Rohtak (28.8955, 76.6066)
                this.map = L.map('radarMap').setView([28.8955, 76.6066], 14);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap &copy; CARTO'
                }).addTo(this.map);

                // Add Retailer Shops with 100-Meter Geofence Circles
                this.retailers.forEach(ret => {
                    const lat = parseFloat(ret.latitude);
                    const lng = parseFloat(ret.longitude);

                    // 100-Meter Radius Circle
                    L.circle([lat, lng], {
                        color: '#059669',
                        fillColor: '#10B981',
                        fillOpacity: 0.15,
                        radius: 100 // Exact 100 meters
                    }).addTo(this.map);

                    // Shop Marker
                    const shopIcon = L.divIcon({
                        className: 'custom-shop-icon',
                        html: `<div style="background:#059669; color:white; border-radius:8px; padding:4px 8px; font-weight:bold; font-size:11px; box-shadow:0 2px 6px rgba(0,0,0,0.2); white-space:nowrap;">🏪 ${ret.shop_name}</div>`,
                        iconSize: [120, 30]
                    });

                    L.marker([lat, lng], { icon: shopIcon })
                        .addTo(this.map)
                        .bindPopup(`<b>${ret.shop_name}</b><br>${ret.address}<br><b>Outstanding:</b> ₹${ret.outstanding_paise/100}`);
                });

                // Add Collector Bike Markers
                this.updateCollectorsOnMap(this.collectors);

                // Setup 10-Second Telemetry Polling Fallback
                setInterval(() => {
                    this.pollCollectorLocations();
                }, 10000);
            },

            updateCollectorsOnMap(collectors) {
                collectors.forEach(col => {
                    if (col.current_lat && col.current_lng) {
                        const lat = parseFloat(col.current_lat);
                        const lng = parseFloat(col.current_lng);

                        const bikeIcon = L.divIcon({
                            className: 'custom-bike-icon',
                            html: `<div style="background:#4F46E5; color:white; border-radius:12px; padding:4px 10px; font-weight:bold; font-size:11px; box-shadow:0 3px 8px rgba(79,70,229,0.4); display:flex; align-items:center; gap:4px; white-space:nowrap;">
                                     <span>🛵</span>
                                     <span>${col.collector_code}</span>
                                     <span style="font-size:9px; background:#3730A3; padding:1px 4px; border-radius:4px;">₹${col.current_float_paise/100}</span>
                                   </div>`,
                            iconSize: [140, 30]
                        });

                        if (this.collectorMarkers[col.id]) {
                            this.collectorMarkers[col.id].setLatLng([lat, lng]);
                        } else {
                            this.collectorMarkers[col.id] = L.marker([lat, lng], { icon: bikeIcon })
                                .addTo(this.map)
                                .bindPopup(`<b>${col.user ? col.user.name : col.collector_code}</b><br>Bike: ${col.bike_number || 'N/A'}<br>Float: ₹${col.current_float_paise/100} / ₹1L<br>Battery: ${col.battery_percent}%`);
                        }
                    }
                });
            },

            pollCollectorLocations() {
                fetch('/api/v1/admin/radar/live-fleet', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(res => res.json())
                .then(json => {
                    if (json.success && json.data.collectors) {
                        this.updateCollectorsOnMap(json.data.collectors);
                    }
                })
                .catch(err => console.log('Radar poll heartbeat:', err));
            }
        }
    }
</script>
@endsection
