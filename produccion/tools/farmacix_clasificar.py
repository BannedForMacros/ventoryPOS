"""
Convierte el Excel de inventario de BOTICA FARMACIX (exportado de su sistema
anterior) en produccion/data/farmacix_productos.json, listo para
produccion/migrar_productos_farmacix.php.

Qué decide:
  · Categorías: las 58 del Excel venían sucias (TABLETA/TABLETAS/PASTILLAS/
    PASTILLA0, "A MPOLLA", "TOALL,HUMEDAS"...) y 636 productos sin ninguna.
    Se normalizan a un catálogo único y los vacíos se clasifican por el nombre.
  · Nombres: MAYÚSCULAS, espacios colapsados y typos evidentes corregidos
    (SHMAPO, DESDORANTE, AMPOLA...). No se tocan nombres de principios activos
    dudosos.
  · Basura: filas "NADA"/"NOSE"/"ANOSEI" (comodines del sistema viejo) se omiten.
  · Duplicados: mismo nombre + mismo precio → uno solo (se conserva el primero).
    Mismo nombre y precio distinto → se conservan ambos, con el código al final.
  · Servicios: "INYECCION" y "ENDOVENOSO" son la aplicación, no un producto.
  · Stock y costo NO se migran: el stock del sistema viejo tiene 352 negativos
    y el costo es 0 en el 92%; se cargarán con una entrada/cierre de inventario.

Uso:  python3 produccion/tools/farmacix_clasificar.py "~/Downloads/EXEL DE PRODUCTOS FARMACIX.xlsx"
"""
import json
import os
import re
import sys
from collections import Counter, defaultdict

import openpyxl

ORIGEN = os.path.expanduser(sys.argv[1] if len(sys.argv) > 1 else '~/Downloads/EXEL DE PRODUCTOS FARMACIX.xlsx')
DESTINO = os.path.join(os.path.dirname(__file__), '..', 'data', 'farmacix_productos.json')

# ── Catálogo final de categorías ────────────────────────────────────────────
TAB = 'Tabletas y cápsulas'
JBE = 'Jarabes y suspensiones'
GTS = 'Gotas'
AMP = 'Ampollas e inyectables'
SOB = 'Sobres y efervescentes'
CRE = 'Cremas, geles y pomadas'
OVU = 'Óvulos'
SPR = 'Sprays e inhaladores'
BEB_ORAL = 'Viales bebibles'
SUE = 'Sueros'
ANT = 'Antisépticos y uso externo'
GRA = 'Productos a granel'
ANTICON = 'Anticonceptivos'
MAT = 'Material médico'
PRU = 'Pruebas de diagnóstico'
ALG = 'Algodón e hisopos'
SHA = 'Shampoo'
ACO = 'Acondicionadores y tratamientos'
TIN = 'Tintes y coloración'
GEL = 'Gel para cabello'
JAB = 'Jabones'
DES = 'Desodorantes'
TAL = 'Talcos'
BUC = 'Cuidado bucal'
FAC = 'Cremas faciales y corporales'
FEM = 'Protección femenina'
HUM = 'Toallas húmedas'
PAN = 'Pañales'
AFE = 'Afeitado'
UNA = 'Cosmética y uñas'
ACE = 'Aceites corporales'
PRE = 'Preservativos y lubricantes'
SOL = 'Bloqueadores y repelentes'
PER = 'Perfumes y colonias'
BEBE = 'Artículos para bebé'
BEBIDAS = 'Bebidas'
NUT = 'Nutrición y suplementos'
CAR = 'Caramelos'
VAR = 'Varios'
SERV = 'Servicios'

# Categoría del Excel → categoría final
MAPA_EXCEL = {
    'TABLETA': TAB, 'TABLETAS': TAB, 'PASTILLAS': TAB, 'PASTILLA': TAB, 'PASTILLA0': TAB, 'CAPSULAS': TAB,
    'JARABE': JBE, 'JARABES': JBE,
    'GOTAS': GTS,
    'AMPOLLA': AMP, 'A MPOLLA': AMP,
    'SOBRES': SOB, 'POLVO EFECVE.': SOB, 'TAB.EFERVECENTES': SOB,
    'POLVO': GRA, 'VASELINA': GRA,
    'CREMA': CRE, 'POMADA': CRE, 'POTE': CRE, 'LATA': CRE, 'LOCION': CRE,
    'OVULOS': OVU,
    'SPRAY': SPR, 'INNALADOR': SPR,
    'BEBIBLES': BEB_ORAL,
    'CLORURO': SUE,
    'SOLUCION USO EXTERNO': ANT, 'ALCOLES': ANT,
    'EQUIPO MEDICO': MAT, 'JERINGAS': MAT, 'ESPÁRADRAPO': MAT, 'VENDITAS': MAT, 'TERMOMETRO': MAT,
    'TES EMBARAZO': PRU,
    'ALGODÓN': ALG, 'HISOPO': ALG,
    'SHAMPO': SHA,
    'ACONDICIONADOR': ACO, 'TRATAMIENTO': ACO,
    'TINTES': TIN,
    'GEL CABELLO': GEL,
    'JABONES': JAB, 'JABON LIQUIDO': JAB,
    'DESODORANTES': DES,
    'TALCOS': TAL,
    'PASTAS DENTAL': BUC, 'CEPILLOS': BUC,
    'CREMAS FACIALES': FAC,
    'TOALLAS': FEM,
    'TOALL,HUMEDAS': HUM,
    'PRESTOBARBA': AFE,
    'QUITA ESMALT': UNA,
    'ACEITE': ACE,
    'PRESERVATIVO': PRE,
    'BEBIDAS': BEBIDAS,
    'CARAMELOS': CAR, 'CARAMELO': CAR,
}

# Reglas por nombre para los que vienen sin categoría. Orden = prioridad:
# lo específico (marca/tipo de producto) antes que la forma farmacéutica.
REGLAS = [
    (SERV, r'^(INYECCION|ENDOVENOSO)$'),
    (ANTICON, r'MENSILLE|MESIGYNA|CYCLOFEMINA|SOLOUNA|SOLUNA|NOFERTYL|FEMTRES|MICROGYNON|FAMILIA 28|NOGESTROL|DAMICOCYN|POSTDAY|ELUDDE'),
    (PRU, r'\bTEST\b|\bTES\b|PRUEBA DE EMB|CONTROLGYN|BABY TES'),
    (PRE, r'PRESERV|DUREX|LUBRICANTE|\bPRES\. PIEL'),
    (SOL, r'BLOQUEADOR|\bBLOQ\.|REPELENTE|\bREP\b|\bREP\.|POST SOLAR'),
    (PAN, r'PAÑAL|BABYSEC|BAYSEC|HUGGIES (VERDE|NATURAL)|NINET PAÑAL|NINET L$|PRACTIPAÑAL|PLENITUD|PRUDENTIAL'),
    (HUM, r'PAÑITOS|TOALLAS? HUMEDAS|TAOLLAS HUMEDAS'),
    (FEM, r'TOALLA (NORMAL|LADYSOFT)|FEM CARE|NOSOTRAS|KOTEX|TAMPONES|PROTECTORES DIARIOS|LADYSOFT'),
    (BEBE, r'BIBERON|CHUPON|MORDEDOR|TETINA|PEZONERA|PROTECTOR P/LACTANCIA|ASPIRADOR NASAL|JUGUETE'),
    (ALG, r'HISOPO|HISPO|ALGOD'),
    (SHA, r'SHAMPO|SHMAPO|SAHAMPO'),
    (ACO, r'ACONDICIONADOR|HIDROCREMA|TRATAMIENTO (SACHETS|SEDAL|SAVITAL)|NUTRIBLEA TRATAMIENTO|NUTRIBELA TRATAMIENTO'),
    (TIN, r'TINTE|OXIGENTA|IGORA|GARNIER|PALETTE'),
    (GEL, r'^EGO '),
    (DES, r'DESO|DESDORANTE'),
    (TAL, r'TALCO'),
    (BUC, r'PASTA (VITIS|DENTAL)|^P\.?D\b|COLGATE|KOLYNOS|CEPILLO|CEPIILO|ENJUAGU?E|ENJ\.|LISTERINE|HILO D|INTERPROX|CINTA DENTAL|CERA VITIS|PERIO AID|COREGA|GINGISONA'),
    (JAB, r'^JABON|MEDICASP SACHETS'),
    (AFE, r'PRESTOBARBA|ESPUMA DE AFEITAR'),
    (UNA, r'QUITA ESMALTE|CORTA UÑAS|LABIAL|LIPSTICK|ZAIDMAN'),
    (PER, r'PERFUME|COLONIA'),
    (FAC, r'NIVEA CREMA|CREMA NIVEA|POND|CONCHA DE NACAR|CREMA CORPORAL|BABARIA|ACNEBIOT'),
    (ACE, r'^ACEITE (JOHNSON|DE COCO|DE ALMENDRAS|ROSADO|RECINO|RICINO)'),
    (BEBIDAS, r'^AGUA (CIELO|SAN CARLOS)|SUEROX|ELECTROL|FRUTIFLEX|LIMONADA'),
    (NUT, r'PEDIASURE|SIMILAC|GERIA|GOMITAS|COLAGEBAL|CITRATO DE MAGNESIO|NUTRIBLEA|ACEITE DE OLIVA|COCTEL DE (VIDA|VITAMINAS)'),
    (CAR, r'CARAMELO|GLORANTA|ACLARANTA'),
    (SUE, r'CLORURO DE SODIO|GLUCOSA'),
    (ANT, r'ALCOHOL|AGUA OXIGENADA|AGUA DE AZAHAR|YOD|VIOLETA DE GENCIANA|TIMOL|ALKOYODO|GENSARNA'),
    (GRA, r'BICARBONATO|ACIDO BORICO|GLICERINA 30|ALUMBRE|AZUFRE BARRA|MANTEQUILLA DE CACAO|VINAGRE|SULFATO DE MAGNESIO|VASELINA'),
    (MAT, r'GUANTES|JERINGA|AGUJA|ABOCAT|BISTURI|HOJA BISTURI|GASA|VENDA|ESPARADRAPO|CINTA ADHESIVA|MASCARILLA|MASCARA C/NEB|GORROS|BAJA LENGUA|EQUIPO|LLAVE TRIPLE|BOLSA RECOLECTORA|BOLSA DE AGUA|POTE (HECES|DE ORINA)|TERMOMETRO|PINZA|AEROCAMARA|AEROKAM|PARCHE|PROTECTOR DE CAMA|SUPOS'),
    (VAR, r'BOLSA DE REGALO|PAPEL|^P\.H\b'),
    # ── Formas farmacéuticas ────────────────────────────────────────────────
    (OVU, r'\bOVU|OVULOS|CREM VAG|CREMA VAGINAL'),
    (SPR, r'SPRAY|SPARY|SPR\b|INHALADOR|INAHALADOR|INNALADOR|PULVERIZACION|PULV CUTANEA'),
    (GTS, r'\bGTS\b|GOTAS|COLIRIO|SOL OFT|0\.3% GTS|\bGTS$'),
    (BEB_ORAL, r'AMP BEBIBLE|RESTOFLORA|ENTEROGERMINA'),
    (AMP, r'\bAMP\b|AMP\.|AMPOLL|AMPOLA|\bINY|INYEC|\bVIAL|ENDOVENOSO|\bIM\b|EV$|DEPOT|DIPROSPAN|DOLFEVER|PASCOE|REFORCE|BENALGIN|ANEURIN|LIDOCAINA|TRAMADOL 100MG|TRAMADOL 50MG|FLEX NF|DICYNONE|OXITOCINA|BELLISIMA|DALCYNVAX|REDEX|DHIPARELAX|KETESSE|SEXSEG|TRAMAL 100MG AMPOLL'),
    (JBE, r'\bJBE\b|\bJB\b|JABE|JARABE|SUSP|SOL ORAL|SOLUCION ORAL|SUSPENSION|X \d+ ?ML$|X\d+ML$|ELITON|WELLPORT|LECHE MAGNESIA|PHILLIPS|ULCIMED|BISMUALIV SUSPENSION'),
    (SOB, r'SOBRE|SOB\b|SOBR\.|EFERV|SACHETS|POLVO|POLV|FLORATIL|PRUNEX|TILO|SALES DE REHIDRATACION|MAGNESOL|BIO MAGNES|VITAPYRENA|KITADOL MIGRAÑA'),
    (CRE, r'CREMA|CREM\b|\bCR\b|GEL\b|GEL X|UNG\b|UNG\.|POMADA|LOCION|MULTIFROS|REUMA ?FROST|REUMOFLEX|HIRUDOID|HIPOGLOS|BEPANTHEN|PENETRO|MENTHOLATUM|VAPORUB|NENEGLOSS|TERRAMIZOL|NOTIPHARM|MEGADERM|MEDIPIEL|VAXIGEL|MUPIROBAC|NOTIL|YODOX'),
    (TAB, r'\bTAB|TABLETA|\bTB\b|\bCAP\b|CAP\.|CAPS|COMP\b|COMP\.|\bMG\b|\d+ ?MG|\bX \d+ UNI|\bREC\b|RECU|\d{2,4}$'),
]

# Correcciones puntuales (código → categoría), para lo que las reglas no
# pueden deducir o el Excel tenía mal.
FORZAR = {
    '00308': BEB_ORAL,   # ENTEROGERMINA venía en JARABE
    '00198': BEB_ORAL,   # RESTOFLORA
    '100922': BEB_ORAL,  # FERROSIL AMP BEBIBLE
    '00311': SPR,        # RINOMAR spray venía en GOTAS
    '00240': AMP,        # METFEVRIL AMP venía en TABLETA
    '00221': SOB,        # BISMUALIV SOBRES venía en TABLETA
    '00248': CAR,        # BRONCOPHAR PLUS CUBOS (pastillas para chupar) venía en JARABE
    '00147': ANTICON, '00148': ANTICON, '00200': ANTICON, '00201': ANTICON,  # venían en PASTILLAS/TABLETAS
    '00207': AMP, '00208': ANTICON, '00209': ANTICON,
    '00277': GRA,        # VASELINA PURA
    '00276': GRA,        # BICARBONATO ALKOF
    '00283': ANT, '00284': ANT,
    '00181': MAT,        # VENDITAS
    '00139': PRU,
    '100982': SUE,       # GLUCOSA (suero glucosado)
    '101012': TAB,       # CIRUELAX TAB
    '100946': TAB,       # COMPLEJO B X 300 CAP
    '100913': SOB,       # VITAMINA C + D3 + ZINC efervescente
    '00405': ACE,        # ACEITE DE COCO COSMETICO
    '00412': ACE, '00413': ACE, '00414': ACE,
    '100949': NUT,       # ACEITE DE OLIVA
    '00406': GRA,        # GLICERINA 30ML
    '00424': GRA,        # MANTEQUILLA DE CACAO
    '00655': GRA,        # SULFATO DE MAGNESIO SOBRE
    '00430': PRE,        # LUBRICANTE PIEL
    '00553': PRE,        # VAXIGEL (lubricante vaginal)
    '00499': JAB,        # MEDICASP SACHETS
    '00436': SHA,        # MEDICASP SHAMPO
    '100953': SHA,       # NOPUCID SACHETS SHAMPO
    '00658': SHA,
    '00584': SHA,
    '100920': SHA,
    '100919': ACO,
    '101011': ACO,       # NUTRIBLEA TRATAMIENTO X 360
    '101006': UNA,       # NIVEA LABIAL
    '00684': UNA,
    '101010': PRU,       # BABY TES DIGITAL
    '00484': PRU, '00486': PRU, '00422': PRU, '00602': PRU, '00792': PRU,
    '00580': SOB,        # FLUIMEXINA 600MG SOBRE
    '00594': SOB,        # BRIMODIN X 30 SOBRES
    '00572': SOB,
    '00749': SOB, '00587': SOB, '00537': SOB, '00782': SOB,
    '00770': SOB, '00771': SOB, '00807': SOB, '00751': SOB, '00752': SOB,
    '00859': SOB, '00835': SOB, '00683': SOB, '00417': SOB,
    '00598': GRA,        # AZUFRE BARRA
    '00437': GRA,        # ALUMBRE
    '00487': GRA,        # VINAGRE BULLY
    '00588': GRA, '00456': GRA, '00454': GRA, '00458': GRA, '00528': GRA, '00759': GRA,
    '00702': ANT, '00707': ANT, '00816': ANT,
    '00419': ANT, '00418': ANT, '100921': ANT,
    '00530': JBE,        # LECHE MAGNESIA
    '00433': JBE, '00448': JBE,
    '00556': JBE,        # WELLPORT NF (solución oral)
    '00834': JBE,
    '00583': JBE,
    '100906': JBE,
    '00253': JBE,
    '00761': GTS, '00755': GTS, '00756': GTS, '00508': GTS, '00586': GTS, '00837': GTS,
    '00797': GTS, '00805': GTS, '00806': GTS, '100998': GTS, '100904': GTS, '100917': GTS,
    '100966': GTS,
    '00630': GTS,        # TRAMADOL GTS
    '00555': SPR,        # AFTOTEX SOLUCION (spray bucal)
    '00611': BUC,        # GINGISONA SPRAY
    '00686': SPR,        # CAPILAXIR solución para pulverización
    '100909': GTS,       # ACTERIL 5MG/ML X10ML SOL.
    '00896': SPR,        # neosyl spray
    '00754': SPR, '00161': SPR,
    '00766': SPR, '00138': SPR,
    '00735': SERV, '00490': SERV,
    '00644': AFE, '00647': AFE,
    '100940': BEBE,      # PACK AMMENS BABY
    '00798': UNA,        # ZAIDMAN ESTUCHE X 3 PIEZAS (set de manicure)
    '00845': BEBE, '00850': BEBE, '00851': BEBE,
    '101021': PER,
    '00801': PER, '100915': PER,
    '101013': VAR, '00387': VAR, '00610': VAR, '100980': VAR, '100986': BEBE,
    '101003': SHA,       # shampo jons sachets
    '00536': SHA, '00549': SHA,
    '00618': SHA,        # HYS SACHETS X 18ML
    '100963': ACO, '00548': ACO,
    '101023': ANT,       # YODOX ESPUMA (yodopovidona)
    '00828': TAB, '00829': TAB,
    '00815': PAN, '00512': PAN, '00513': PAN, '00514': PAN, '00452': PAN, '00728': PAN,
    '00533': FEM,
    '00787': JAB,        # JABON INTIMO NOSOTRAS
    '00705': FEM,
    '00685': SOL, '00697': SOL, '00545': SOL,
    '00596': FAC,        # acnebiot
    '00825': CRE,
    '00440': CRE,
    '00336': TAB,
    '00404': AMP,        # OMEPRAZOL POLVO INYEC X 10 VIALES
    '00288': AMP,        # BACZOLE X10VIALES
    '00731': AMP, '00733': AMP, '00734': AMP, '00643': AMP, '00642': AMP,
    '00483': AMP,
    '00606': AMP,
    '00820': BUC,        # COLGATE
    '00622': BUC,
    '00392': BUC,
    '00718': BUC,
    '00660': BUC,
    '00778': BUC,
    '00664': BEBE, '00557': BEBE, '00474': BEBE, '00479': BEBE, '00472': BEBE,
    '00577': BEBE,
    '00757': BEBE,
    '00691': TAB,        # PARDIL 500MG X 6 TAB
    '00821': TAB,
    '00547': NUT,
    '00389': NUT,
    '00415': BEBIDAS,
    '00561': BEBIDAS,
    '00511': BEBIDAS, '00390': BEBIDAS, '00391': BEBIDAS,
    '100978': NUT,
    '100902': NUT,
    '00833': NUT,
    '00744': NUT, '00745': NUT, '00746': NUT, '00747': NUT,
    '100990': NUT, '100989': NUT,
    '100991': AMP,       # REFORCE AMP. EV
    # Sin pista en el nombre: resueltos a mano.
    '100900': JBE,       # BRONCOTRIN DILAT
    '00600': AMP,        # CEFTRI DS 1GR (ceftriaxona inyectable)
    '00445': BEBIDAS,    # SPORADE (bebida rehidratante)
    '101000': TAB, '100992': TAB, '00894': TAB, '00819': TAB, '00569': TAB, '00573': TAB,
    '00595': TAB, '00604': TAB, '00626': TAB, '00629': TAB, '00475': TAB, '00520': TAB,
    '00532': TAB, '00534': TAB, '00388': TAB, '00393': TAB, '00399': TAB, '00431': TAB,
}
# Supositorios de glicerina: forma farmacéutica propia.
SUP = 'Supositorios'
FORZAR['00708'] = SUP
FORZAR['00646'] = SUP
FORZAR['100962'] = JAB   # LACTACYD (jabón íntimo líquido)
FORZAR['101004'] = DES   # DES SECRET GEL (desodorante en gel)

# Typos evidentes del sistema viejo. Solo palabras completas.
TYPOS = [
    (r'\bSHMAPO\b|\bSAHAMPO\b|\bSHAMPO\b', 'SHAMPOO'),
    (r'\bDESDORANTE\b', 'DESODORANTE'),
    (r'\bTOALL\.HUMEDAS\b', 'TOALLAS HUMEDAS'),
    (r'\bTAOLLAS\b', 'TOALLAS'),
    (r'\bTRTATAMIENTO\b', 'TRATAMIENTO'),
    (r'\bSPARY\b', 'SPRAY'),
    (r'\bCEPIILO\b', 'CEPILLO'),
    (r'\bHISPO\b', 'HISOPO'),
    (r'\bBAYSEC\b', 'BABYSEC'),
    (r'\bINAHALADOR\b|\bINNALADOR\b', 'INHALADOR'),
    (r'\bDFENTAL\b', 'DENTAL'),
    (r'\bADULRO\b', 'ADULTO'),
    (r'\bJABE\b', 'JBE'),
    (r'\bAMPOLA\b|\bAMPOLL\b', 'AMPOLLA'),
    (r'\bROOL-ON\b', 'ROLL-ON'),
    (r'\bCOMPUIESTO\b', 'COMPUESTO'),
    (r'\bJUNIIOR\b', 'JUNIOR'),
    (r'\bINVESIBLE\b', 'INVISIBLE'),
    (r'\bKETEROLACO\b', 'KETOROLACO'),
    (r'\bPRDNISONA\b', 'PREDNISONA'),
    (r'\bTERFINAFINA\b', 'TERBINAFINA'),
    (r'\bFUROSAMINA\b', 'FUROSEMIDA'),
    (r'\bEFERVECENTES\b', 'EFERVESCENTES'),
    (r'\bENJUAGE\b', 'ENJUAGUE'),
    (r'\bJOHSON\b|\bJHONSON\b|\bJONS\b', 'JOHNSON'),
    (r'\bGILLETE\b', 'GILLETTE'),
    (r'\bEFFICENT\b', 'EFFICIENT'),
    (r'\bCLARATNT\b', 'CLARANT'),
    (r'\bPREMIUN\b', 'PREMIUM'),
    (r'\bEXTENCION\b', 'EXTENSION'),
    (r'\bMISOPROSOL\b', 'MISOPROSTOL'),
    (r'\bRECINO\b', 'RICINO'),
    (r'\bPOND S S\b|\bPOND S\b', 'PONDS'),
    (r'\bX(\d)', r'X \1'),       # X500ML → X 500ML
    (r'(\d)X\b', r'\1 X'),       # 50X → 50 X (no toca "10X10")
]

BASURA = re.compile(r'^(NADA|NOSE|ANOSEI)$')


def limpiar_nombre(n: str) -> str:
    n = re.sub(r'\s+', ' ', str(n)).strip().upper()
    for patron, reemplazo in TYPOS:
        n = re.sub(patron, reemplazo, n)
    return re.sub(r'\s+', ' ', n).strip()


def clasificar(codigo, nombre, cat_excel):
    if codigo in FORZAR:
        return FORZAR[codigo], 'forzado'
    if cat_excel:
        return MAPA_EXCEL[cat_excel.strip()], 'excel'
    for categoria, patron in REGLAS:
        if re.search(patron, nombre):
            return categoria, 'regla'
    return None, 'sin'


def main():
    ws = openpyxl.load_workbook(ORIGEN, data_only=True).active
    filas = [r for r in ws.iter_rows(min_row=6, values_only=True) if r[1]]  # sin totales

    productos, omitidos, vistos = [], [], {}
    for r in filas:
        codigo = str(r[4]).strip()
        original = str(r[2])
        nombre = limpiar_nombre(original)
        precio = round(float(r[10] or 0), 2)

        if BASURA.match(nombre):
            omitidos.append({'codigo': codigo, 'nombre': original, 'motivo': 'comodín sin nombre'})
            continue

        clave = (nombre, precio)
        if clave in vistos:
            omitidos.append({'codigo': codigo, 'nombre': original, 'motivo': f'duplicado de {vistos[clave]}'})
            continue
        vistos[clave] = codigo

        categoria, origen = clasificar(codigo, nombre, r[6])
        productos.append({
            'codigo': codigo,
            'nombre': nombre,
            'nombre_original': original,
            'categoria': categoria,
            'origen_categoria': origen,
            'tipo': 'servicio' if categoria == SERV else 'producto',
            'precio': precio,
        })

    # Mismo nombre y precio distinto: se distinguen con el código.
    por_nombre = defaultdict(list)
    for p in productos:
        por_nombre[p['nombre']].append(p)
    for nombre, grupo in por_nombre.items():
        if len(grupo) > 1:
            for p in grupo:
                p['nombre'] = f"{nombre} ({p['codigo']})"

    for p in productos:
        if len(p['nombre']) > 150:
            p['nombre'] = p['nombre'][:150]

    sin = [p for p in productos if not p['categoria']]
    json.dump({'productos': productos, 'omitidos': omitidos}, open(DESTINO, 'w'), ensure_ascii=False, indent=1)

    print(f'{len(productos)} productos, {len(omitidos)} omitidos, {len(sin)} sin categoría')
    for categoria, n in sorted(Counter(p['categoria'] for p in productos).items(), key=lambda x: -x[1]):
        print(f'  {n:4d}  {categoria}')
    for p in sin:
        print('  SIN:', p['codigo'], p['nombre'])


if __name__ == '__main__':
    main()
