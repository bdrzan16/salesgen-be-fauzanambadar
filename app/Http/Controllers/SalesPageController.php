<?php

namespace App\Http\Controllers;

use App\Models\SalesPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SalesPageController extends Controller
{
    public function index()
    {
        $pages = SalesPage::where('user_id', Auth::id())->latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $pages
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_name'    => 'required|string',
            'description'     => 'required|string',
            'features'        => 'required|string',
            'target_audience' => 'required|string',
            'price'           => 'required|string',
            'usp'             => 'required|string',
        ]);

        try {
            $apiKey = env('GEMINI_API_KEY');
            
            $prompt = "Buatlah landing page sales page yang sangat persuasif dan profesional untuk produk: {$validated['product_name']}. 
            Deskripsi: {$validated['description']}. 
            Fitur: {$validated['features']}. 

            IKUTI ATURAN BERIKUT:
            1. Gunakan HTML5 murni dengan CDN Tailwind CSS (https://cdn.tailwindcss.com).
            2. Desain harus modern, responsif, dan memiliki padding/margin yang seimbang.
            3. Sertakan Hero Section (Headline & Subheadline), Features Section (Grid), dan CTA Section (Tombol Menarik).
            4. Pilih palet warna yang paling cocok dengan branding produk ini.
            5. Berikan jawaban HANYA berupa kode HTML di dalam tag <div>...</div>.
            6. JANGAN gunakan markdown seperti ```html atau penjelasan teks apapun. Cukup kode HTML-nya saja.";

            $response = Http::timeout(100)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            $result = $response->json();

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Detail Error Google: ' . $result['error']['message']
                ], 400);
            }

            $htmlRaw = $result['candidates'][0]['content']['parts'][0]['text'];

            $cleanHtml = preg_replace('/^```html\s*|```$/m', '', $htmlRaw);
            $cleanHtml = trim($cleanHtml);

            $salesPage = SalesPage::create([
                'user_id'         => Auth::id(),
                'product_name'    => $validated['product_name'],
                'description'     => $validated['description'],
                'features'        => $validated['features'],
                'target_audience' => $validated['target_audience'],
                'price'           => $validated['price'],
                'usp'             => $validated['usp'],
                'ai_content'      => $cleanHtml,
            ]);

            return response()->json(['success' => true, 'data' => $salesPage], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $salesPage = SalesPage::where('user_id', Auth::id())->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $salesPage
        ]);
    }

    public function update(Request $request, $id)
    {
        $salesPage = SalesPage::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'product_name'    => 'required|string',
            'description'     => 'required|string',
            'features'        => 'required|string',
            'target_audience' => 'required|string',
            'price'           => 'required|string',
            'usp'             => 'required|string',
        ]);

        $salesPage->update($validated);

        try {
            $newHtml = $this->generateAiLayout($validated, $salesPage->ai_content); 
            $salesPage->update(['ai_content' => $newHtml]);
            $message = 'Data dan Layout berhasil diperbarui';
        } catch (\Exception $e) {
            $message = 'Data tersimpan, tapi gagal memperbarui layout AI: ' . $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $salesPage->refresh()
        ]);
    }

    private function generateAiLayout($data, $oldContent)
    {
        $apiKey = env('GEMINI_API_KEY');

        $prompt = "Generate a professional landing page HTML with Tailwind CSS. 
                Product Name: {$data['product_name']}. 
                Description: {$data['description']}. 
                Features: {$data['features']}. 
                Target Audience: {$data['target_audience']}.
                Price: {$data['price']}.
                Return ONLY the raw HTML code without code blocks or explanations.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ]
            ]);

            if ($response->successful()) {
                $resData = $response->json();
                $html = $resData['candidates'][0]['content']['parts'][0]['text'];
                $html = str_replace(['```html', '```'], '', $html);
                
                return trim($html);
            }
            
            return $oldContent;
        } catch (\Exception $e) {
            return $oldContent;
        }
    }

    public function destroy($id)
    {
        $page = SalesPage::where('user_id', Auth::id())->findOrFail($id);
        $page->delete();
        
        return response()->json(['success' => true, 'message' => 'Berhasil dihapus']);
    }
}
