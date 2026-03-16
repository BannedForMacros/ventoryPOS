import React from 'react';
import * as LucideIcons from 'lucide-react';
import type { LucideProps } from 'lucide-react';

interface DynamicIconProps extends LucideProps {
    name: string;
}

export default function DynamicIcon({ name, ...props }: DynamicIconProps) {
    const icons = LucideIcons as unknown as Record<string, React.ComponentType<LucideProps>>;
    const Icon = icons[name];
    if (!Icon) return <LucideIcons.Box {...props} />;
    return <Icon {...props} />;
}
