<?php

namespace App\Http\Middleware;

use App\Support\EdisiTerpilih;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan edisi untuk halaman publik dan membagikannya ke semua view
 * sebagai $edisi dan $edisiTerpilih.
 */
class TetapkanEdisi
{
    public function __construct(private readonly EdisiTerpilih $edisiTerpilih) {}

    public function handle(Request $request, Closure $next): Response
    {
        $edisi = $this->edisiTerpilih->resolve($request);

        // Tahun di ?edisi= tidak dikenal. (Tanpa edisi sama sekali → edisi sementara, halaman tetap tampil.)
        abort_if(! $edisi, 404);

        // URL kanonik: ?edisi=<tahun edisi aktif> → alamat yang sama tanpa parameter.
        if ($request->filled('edisi') && $edisi->is_aktif && $request->isMethod('GET')) {
            return redirect()->to($request->fullUrlWithoutQuery(['edisi']), 301);
        }

        View::share('edisi', $edisi);
        View::share('edisiTerpilih', $this->edisiTerpilih);

        return $next($request);
    }
}
