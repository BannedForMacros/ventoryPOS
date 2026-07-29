<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\VincularEmisor;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Configuración → Facturación Electrónica.
 *
 * ─── Por qué existe esta pantalla ────────────────────────────────────────────────
 *
 * La conexión con el emisor vivía en el `.env`: FACTURAMAC_ENABLED y
 * FACTURAMAC_TOKEN, uno para toda la instalación. Dos problemas a la vez:
 *
 *  1. Un token identifica a UNA empresa emisora, así que con dos contribuyentes en
 *     la misma instalación (MacSoft y HYC FERROMATERIALES) las ventas de la segunda
 *     salían con el RUC de la primera.
 *  2. Cambiarlo exigía entrar por SSH y editar un archivo. El requisito del dueño es
 *     tajante: TODO por interfaz.
 *
 * Aquí se resuelven los dos: una conexión por empresa, gestionada desde el navegador
 * con un código corto de un solo uso. El usuario no teclea URLs ni tokens.
 *
 * ─── Qué NO se puede hacer desde aquí ────────────────────────────────────────────
 *
 * Cambiar la URL del emisor (es despliegue, vive en el `.env`) y tocar nada fiscal:
 * series, tasa, umbral y modo son competencia de FacturaMac. Esta pantalla los
 * MUESTRA; quien los decide es quien firma el comprobante.
 */
class FacturacionElectronicaController extends Controller
{
    public function __construct(
        private readonly FacturacionEmpresa $facturacion,
        private readonly VincularEmisor $vincular,
    ) {}

    public function index(Request $request)
    {
        $empresa = $request->user()->empresa;

        return Inertia::render('Configuracion/FacturacionElectronica', $this->estado($empresa));
    }

    /**
     * Conectar con un código corto. Es TODO el flujo: un campo y un botón.
     *
     * Las dos guardas (RUC que coincide, y modo producción para poder emitir) están
     * en `VincularEmisor`, no aquí: son reglas del dominio, no de la pantalla, y
     * tienen que valer igual si algún día se conecta desde otro sitio.
     */
    public function conectar(Request $request)
    {
        $empresa = $request->user()->empresa;

        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
        ], [
            'codigo.required' => 'Pega el código de vinculación que te dio FacturaMac.',
        ]);

        $conexion = $this->vincular->conectar(
            $empresa,
            $datos['codigo'],
            // Nombre libre con el que el emisor identificará esta instalación en su
            // listado de vinculaciones. Se compone solo para que nadie tenga que
            // escribirlo: el flujo es un campo y un botón.
            $this->nombreInstalacion($empresa),
        );

        AuditoriaService::log('facturacion.conectada', $empresa, [
            'ruc_emisor'  => $conexion->ruc_emisor,
            'razon_social'=> $conexion->razon_social_emisor,
            'modo'        => $conexion->modo,
        ], $request->user());

        // El mensaje distingue los dos finales posibles, porque son muy distintos:
        // conectado y listo, o conectado pero sin poder emitir todavía.
        $mensaje = $conexion->puedeEmitir()
            ? "Conectado con {$conexion->razon_social_emisor} (RUC {$conexion->ruc_emisor}). "
                . 'Ya puedes activar la emisión.'
            : "Conectado con {$conexion->razon_social_emisor} (RUC {$conexion->ruc_emisor}), "
                . 'pero FacturaMac tiene esta empresa en modo ' . mb_strtoupper($conexion->modo)
                . ': todavía no se puede activar la emisión.';

        return back()->with('success', $mensaje);
    }

    /**
     * Enciende o apaga la emisión de esta empresa.
     *
     * Es el interruptor que sustituye a FACTURAMAC_ENABLED, y el único camino para
     * moverlo. Si el emisor tiene la empresa en beta o simulación, `VincularEmisor`
     * lanza con el mensaje que explica por qué no se puede.
     */
    public function emision(Request $request)
    {
        $empresa = $request->user()->empresa;

        $datos = $request->validate([
            'emision_activa' => ['required', 'boolean'],
        ]);

        $conexion = $this->vincular->activar($empresa, (bool) $datos['emision_activa']);

        AuditoriaService::log('facturacion.emision_' . ($conexion->emision_activa ? 'activada' : 'desactivada'), $empresa, [
            'ruc_emisor' => $conexion->ruc_emisor,
            'modo'       => $conexion->modo,
        ], $request->user());

        return back()->with('success', $conexion->emision_activa
            ? 'Emisión electrónica ACTIVADA. Las boletas y facturas de esta empresa se informarán a SUNAT.'
            : 'Emisión electrónica desactivada. Las ventas seguirán registrándose, pero no se informarán a SUNAT.');
    }

    /** Prueba la conexión y refresca el modo. No cambia nada fiscal. */
    public function probar(Request $request)
    {
        $empresa = $request->user()->empresa;

        $resultado = $this->vincular->diagnosticar($empresa);

        return back()->with(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje'],
        );
    }

    /** Desconecta la empresa. Sin token no se emite nada. */
    public function desconectar(Request $request)
    {
        $empresa  = $request->user()->empresa;
        $conexion = $this->facturacion->conexion($empresa->id);

        $this->vincular->desconectar($empresa);

        AuditoriaService::log('facturacion.desconectada', $empresa, [
            'ruc_emisor' => $conexion?->ruc_emisor,
        ], $request->user());

        return back()->with('success', 'Empresa desconectada de FacturaMac. Ya no se emitirá ningún comprobante.');
    }

    /**
     * Lo que la pantalla necesita saber en todo momento: a qué emisor está
     * conectada, con qué RUC, en qué modo y si la emisión está activa.
     *
     * El token NO sale de aquí en ninguna forma —ni recortado ni enmascarado—. Es la
     * credencial que firma comprobantes fiscales; enseñarla lo único que aporta es
     * dejarla en el HTML de la página.
     *
     * @return array<string, mixed>
     */
    private function estado(Empresa $empresa): array
    {
        $conexion = $this->facturacion->conexion($empresa->id);

        return [
            'empresa' => [
                'id'           => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'ruc'          => $empresa->ruc,
            ],
            // Si es false, el SQL de producción no se ha ejecutado todavía. La
            // pantalla lo dice con todas las letras en vez de fallar al guardar.
            'instalado' => $this->facturacion->tablaDisponible(),
            // Informativo: el usuario no la teclea, pero saber contra qué servidor
            // se está hablando es lo primero que se pregunta cuando algo falla.
            'emisorUrl' => (string) config('facturamac.base_url'),
            'conexion'  => $conexion === null ? null : [
                'ruc_emisor'          => $conexion->ruc_emisor,
                'razon_social_emisor' => $conexion->razon_social_emisor,
                'modo'                => $conexion->modo,
                'emision_activa'      => $conexion->emision_activa,
                'instalacion'         => $conexion->instalacion,
                'conectado_at'        => $conexion->conectado_at,
                'diagnostico_at'      => $conexion->diagnostico_at,
                'diagnostico_ok'      => $conexion->diagnostico_ok,
                'diagnostico_mensaje' => $conexion->diagnostico_mensaje,
                // Se calcula en el servidor, no en el navegador: es la misma regla
                // que aplica `VincularEmisor::activar()`, y dos copias de una regla
                // solo pueden estar de acuerdo o desincronizadas.
                'puede_activar'       => $conexion->puedeEmitir(),
                'en_pruebas'          => $conexion->enPruebas(),
                // ¿Está emitiendo AHORA MISMO? No es lo mismo que `emision_activa`:
                // una empresa activada a la que el emisor devolvió a beta tiene el
                // interruptor en on y no emite. La pantalla debe decir la verdad.
                'emite'               => $conexion->emite(),
            ],
        ];
    }

    /**
     * Nombre con el que esta instalación aparece en FacturaMac. Se compone solo
     * —razón social + host— para que el flujo siga siendo un campo y un botón.
     */
    private function nombreInstalacion(Empresa $empresa): string
    {
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ventoryPOS');

        return mb_substr("{$empresa->razon_social} · {$host}", 0, 120);
    }
}
