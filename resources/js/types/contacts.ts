export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff';
    is_active: boolean;
}

export interface Category {
    id: number;
    name: string;
    description?: string | null;
}

export interface Barangay {
    id: number;
    name: string;
}

export interface Contact {
    id: number;
    first_name: string;
    middle_initial?: string | null;
    last_name: string;
    office?: string | null;
    position?: string | null;
    phone_number: string;
    category_id: number;
    barangay_id: number | null;
    is_active: boolean;
    category?: Category;
    barangay?: Barangay | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    links: PaginationLink[];
}

export interface ContactsPageProps {
    [key: string]: unknown;
    directoryStats: { total: number; active: number; barangays: number };
    permissions: { operate: boolean; administer: boolean };
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
    };
    contacts: PaginatedData<Contact>;
    categories: Category[];
    barangays: Barangay[];
    filters: {
        search?: string;
        barangay_id?: string;
        category_id?: string;
    };
}
