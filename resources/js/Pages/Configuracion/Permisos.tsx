import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Checkbox from '@/Components/UI/Checkbox';
import type { Modulo, PageProps, Permiso, Rol } from '@/types';

interface Props extends PageProps {
    roles: Rol[];
    modulos: Modulo[];
    rolSeleccionado: Rol | null;
    permisos: Permiso[];
}

interface PermisoRow {
    modulo_id: number;
    ver: boolean;
    crear: boolean;
    editar: boolean;
    eliminar: boolean;
}

const ACCIONES: Array<'ver' | 'crear' | 'editar' | 'eliminar'> = ['ver', 'crear', 'editar', 'eliminar'];

export default function Permisos({ roles, modulos, rolSeleccionado, permisos }: Props) {
    const { flash } = usePage<Props>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const [rolId, setRolId] = useState(rolSeleccionado ? String(rolSeleccionado.id) : '');
    const [saving, setSaving] = useState(false);

    function buildRows(mods: Modulo[]): PermisoRow[] {
        const rows: PermisoRow[] = [];
        mods.forEach(mod => {
            const existing = permisos.find(p => p.modulo_id === mod.id);
            rows.push({
                modulo_id: mod.id,
                ver: existing?.ver ?? false,
                crear: existing?.crear ?? false,
                editar: existing?.editar ?? false,
                eliminar: existing?.eliminar ?? false,
            });
            if (mod.hijos && mod.hijos.length > 0) {
                mod.hijos.forEach(hijo => {
                    const ex = permisos.find(p => p.modulo_id === hijo.id);
                    rows.push({
                        modulo_id: hijo.id,
                        ver: ex?.ver ?? false,
                        crear: ex?.crear ?? false,
                        editar: ex?.editar ?? false,
                        eliminar: ex?.eliminar ?? false,
                    });
                });
            }
        });
        return rows;
    }

    const [rows, setRows] = useState<PermisoRow[]>(() => buildRows(modulos));

    useEffect(() => {
        setRows(buildRows(modulos));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [permisos, modulos]);

    function changeRol(value: string | number) {
        const id = String(value);
        setRolId(id);
        if (id) {
            router.get(route('configuracion.permisos.index'), { rol_id: id }, { preserveState: false });
        }
    }

    function toggle(moduloId: number, accion: 'ver' | 'crear' | 'editar' | 'eliminar') {
        setRows(prev => prev.map(r => r.modulo_id === moduloId ? { ...r, [accion]: !r[accion] } : r));
    }

    function toggleAll(moduloId: number, value: boolean) {
        setRows(prev => prev.map(r =>
            r.modulo_id === moduloId
                ? { ...r, ver: value, crear: value, editar: value, eliminar: value }
                : r
        ));
    }

    function save() {
        if (!rolSeleccionado) return;
        setSaving(true);
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(
            route('configuracion.permisos.store', rolSeleccionado.id),
            { permisos: rows } as any,
            { onFinish: () => setSaving(false) }
        );
    }

    function getModuloNombre(id: number): string {
        for (const m of modulos) {
            if (m.id === id) return m.nombre;
            if (m.hijos) {
                const h = m.hijos.find(h => h.id === id);
                if (h) return h.nombre;
            }
        }
        return String(id);
    }

    function getPadreNombre(id: number): string | null {
        for (const m of modulos) {
            if (m.hijos?.find(h => h.id === id)) return m.nombre;
        }
        return null;
    }

    const rolOptions = roles.map(r => ({
        value: String(r.id),
        label: `${r.nombre}${r.empresa ? ` (${r.empresa.nombre_comercial ?? r.empresa.razon_social})` : ''}`,
    }));

    return (
        <AppLayout title="Permisos por Rol">
            <PageHeader
                title="Permisos por Rol"
                subtitle="Configura los permisos de acceso por módulo para cada rol"
                actions={
                    rolSeleccionado && (
                        <Button loading={saving} onClick={save}>Guardar Permisos</Button>
                    )
                }
            />

            <div className="mb-6 max-w-xs">
                <Select
                    label="Seleccionar Rol"
                    options={rolOptions}
                    value={rolId}
                    onChange={changeRol}
                    placeholder="— Seleccione un rol —"
                />
            </div>

            {rolSeleccionado && (
                <div
                    className="rounded-2xl border overflow-x-auto"
                    style={{
                        borderColor: 'var(--color-border)',
                        backgroundColor: 'var(--color-surface)',
                        boxShadow: '0 1px 4px 0 rgb(0 0 0 / 0.04)',
                    }}
                >
                    <table className="min-w-full divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        <thead style={{ backgroundColor: 'var(--color-bg)' }}>
                            <tr>
                                <th
                                    className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider"
                                    style={{ color: 'var(--color-text-muted)', width: '40%' }}
                                >
                                    Módulo
                                </th>
                                <th
                                    className="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                                    style={{ color: 'var(--color-text-muted)' }}
                                >
                                    Todos
                                </th>
                                {ACCIONES.map(a => (
                                    <th
                                        key={a}
                                        className="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--color-text-muted)' }}
                                    >
                                        {a.charAt(0).toUpperCase() + a.slice(1)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => {
                                const padre = getPadreNombre(row.modulo_id);
                                const nombre = getModuloNombre(row.modulo_id);
                                const allChecked = ACCIONES.every(a => row[a]);
                                return (
                                    <tr
                                        key={row.modulo_id}
                                        style={{ borderTop: index !== 0 ? '1px solid var(--color-border)' : undefined }}
                                        onMouseEnter={e => (e.currentTarget.style.backgroundColor = 'var(--color-bg)')}
                                        onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}
                                    >
                                        <td className="px-5 py-3.5 text-sm" style={{ color: 'var(--color-text)' }}>
                                            {padre && (
                                                <span className="text-xs mr-1" style={{ color: 'var(--color-text-muted)' }}>
                                                    {padre} /
                                                </span>
                                            )}
                                            {nombre}
                                        </td>
                                        <td className="px-5 py-3.5 text-center">
                                            <Checkbox
                                                name={`all_${row.modulo_id}`}
                                                checked={allChecked}
                                                onChange={e => toggleAll(row.modulo_id, e.target.checked)}
                                            />
                                        </td>
                                        {ACCIONES.map(a => (
                                            <td key={a} className="px-5 py-3.5 text-center">
                                                <Checkbox
                                                    name={`${a}_${row.modulo_id}`}
                                                    checked={row[a]}
                                                    onChange={() => toggle(row.modulo_id, a)}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {!rolSeleccionado && (
                <div
                    className="rounded-2xl border p-12 text-center text-sm"
                    style={{
                        borderColor: 'var(--color-border)',
                        color: 'var(--color-text-muted)',
                        backgroundColor: 'var(--color-surface)',
                    }}
                >
                    Selecciona un rol para gestionar sus permisos.
                </div>
            )}
        </AppLayout>
    );
}
