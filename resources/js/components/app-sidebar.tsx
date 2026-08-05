import { Link, usePage } from '@inertiajs/react';
import { FileSearch, FileText, Headset, KeyRound, Layers, LayoutGrid, Receipt, ScrollText, Search, Server, Settings, Users, Wallet, Webhook } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const clientNav: NavItem[] = [
    { title: 'Painel', href: '/dashboard', icon: LayoutGrid },
    { title: 'Consultas', href: '/client/consultations', icon: Search },
    { title: 'Financeiro', href: '/client/billing', icon: Wallet },
    { title: 'Minhas Faturas', href: '/client/invoices', icon: Receipt },
    { title: 'Suporte / Chamados', href: '/client/tickets', icon: Headset },
    { title: 'Minhas chaves', href: '/client/api-keys', icon: KeyRound },
    { title: 'Webhook', href: '/client/webhook', icon: Webhook },
    { title: 'Logs', href: '/client/logs', icon: ScrollText },
];

const adminNav: NavItem[] = [
    { title: 'Painel', href: '/admin', icon: LayoutGrid },
    { title: 'Clientes', href: '/admin/accounts', icon: Users },
    { title: 'Financeiro', href: '/admin/finance', icon: Wallet },
    { title: 'Planos', href: '/admin/plans', icon: Layers },
    { title: 'Tickets de suporte', href: '/admin/tickets', icon: Headset },
    { title: 'Tipos de consulta', href: '/admin/query-types', icon: FileSearch },
    { title: 'Provedores', href: '/admin/providers', icon: Server },
    { title: 'Logs', href: '/admin/logs', icon: ScrollText },
    { title: 'Configurações', href: '/admin/settings', icon: Settings },
];

const footerNavItems: NavItem[] = [
    { title: 'Documentação da API', href: '/docs/api', icon: FileText },
];

export function AppSidebar() {
    const page = usePage<{
        auth: { user: { role?: string } | null };
        unread_support_tickets?: number;
    }>();
    const isAdmin = page.props.auth?.user?.role === 'admin';
    const unread = page.props.unread_support_tickets ?? 0;
    const mainNavItems = (isAdmin ? adminNav : clientNav).map((item) =>
        item.href.endsWith('/tickets') ? { ...item, badge: unread } : item,
    );
    const homeHref = isAdmin ? '/admin' : '/dashboard';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:p-1!"
                        >
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
