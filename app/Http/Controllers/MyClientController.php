<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MyClient;
use Illuminate\Support\Facades\Redis;


class MyClientController extends Controller
{
    public function show($slug) {
        if (Redis::exists($slug)) {
            $data = json_decode(Redis::get($slug), true);
            return response()->json($data);
        }

        $client = MyClient::where('slug', $slug)->whereNull('deleted_at')->first();

        Redis::set($slug, json_encode($client));
        Redis::persist($slug);

        return response()->json(['data' => $client]);
    }


    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100',
            'is_project' => 'in:0,1',
            'self_capture' => 'in:0,1',
            'client_prefix' => 'required|string|max:4',
            'client_logo' => 'nullable|image',
        ]);

        if ($request->hasFile('client_logo')) {
            $path = $request->file('client_logo')->store('client_logo', 's3');
            $validated['client_logo'] = Storage::disk('s3')->url($path);
        }

        $client = MyClient::create($validated);

        Redis::set($client->slug, json_encode($client));
        Redis::persist($client->slug);

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function update(Request $request, $id) {
        $client = MyClient::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:250',
            'slug' => 'string|max:100|unique:my_client,slug,' . $client->id,
            'is_project' => 'in:0,1',
            'self_capture' => 'in:0,1',
            'client_prefix' => 'string|max:4',
            'client_logo' => 'nullable|image',
        ]);

        if ($request->hasFile('client_logo')) {
            $path = $request->file('client_logo')->store('client_logo', 's3');
            $validated['client_logo'] = Storage::disk('s3')->url($path);
        }

        Redis::del($client->slug); 

        $client->update($validated);

        Redis::set($client->slug, json_encode($client));
        Redis::persist($client->slug);

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function destroy($id)
    {
        $client = MyClient::findOrFail($id);
        $client->delete();

        Redis::del($client->slug);

        return response()->json(['success' => true]);
    }


}
