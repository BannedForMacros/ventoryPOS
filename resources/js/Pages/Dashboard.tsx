import { Building2, Layers, Shield, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';

interface Stats {
    empresas: number;
    usuarios: number;
    roles: number;
    modulos: number;
}

interface Props extends PageProps {
    stats: Stats;
}

interface StatCardProps {
    label: string;
    value: number;
    icon: React.ReactNode;
    color: string;
}

function StatCard({ label, value, icon, color }: StatCardProps) {
    return (
        <div
            className="rounded-lg border p-6 flex items-start gap-4"
            style={{
                backgroundColor: 'var(--color-surface)',
                borderColor: 'var(--color-border)',
            }}
        >
            <div
                className="flex h-12 w-12 items-center justify-center rounded-lg flex-shrink-0"
                style={{ backgroundColor: color + '18', color }}
            >
                {icon}
            </div>
            <div>
                <p className="text-2xl font-bold" style={{ color: 'var(--color-text)' }}>{value}</p>
                <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{label}</p>
            </div>
        </div>
    );
}

export default function Dashboard({ stats }: Props) {
    return (
        <AppLayout title="Dashboard">
            <div className="mb-6">
                <h2 className="text-lg font-semibold" style={{ color: 'var(--color-text)' }}>Resumen del sistema</h2>
                <p className="text-sm mt-0.5" style={{ color: 'var(--color-text-muted)' }}>Vista general de los datos registrados</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    label="Empresas"
                    value={stats?.empresas ?? 0}
                    icon={<Building2 size={22} />}
                    color="var(--color-primary)"
                />
                <StatCard
                    label="Usuarios"
                    value={stats?.usuarios ?? 0}
                    icon={<Users size={22} />}
                    color="var(--color-success)"
                />
                <StatCard
                    label="Roles"
                    value={stats?.roles ?? 0}
                    icon={<Shield size={22} />}
                    color="var(--color-warning)"
                />
                <StatCard
                    label="Módulos"
                    value={stats?.modulos ?? 0}
                    icon={<Layers size={22} />}
                    color="var(--color-secondary)"
                />
            </div>
        </AppLayout>
    );
}
