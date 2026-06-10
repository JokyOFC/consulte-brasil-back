import { BrandLogo, BrandMark } from '@/components/brand-logo';

export default function AppLogo() {
    return (
        <>
            <BrandLogo className="h-11 w-auto max-w-full shrink-0 group-data-[collapsible=icon]:hidden" />
            <BrandMark className="hidden size-10 shrink-0 group-data-[collapsible=icon]:block" />
        </>
    );
}
