import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'className'> & {
    icon: LucideIcon;
    className?: string;
    inputClassName?: string;
};

export default function IconInput({
    icon: Icon,
    className,
    inputClassName,
    ...props
}: Props) {
    return (
        <div className={cn('relative', className)}>
            <Icon
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input className={cn('pl-9', inputClassName)} {...props} />
        </div>
    );
}
