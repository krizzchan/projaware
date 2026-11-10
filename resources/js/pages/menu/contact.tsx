import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useState } from 'react';

// Starter Kit Layout
import AppLayout from '@/layouts/app-layout';

// Components
import InputError from '@/components/input-error';

// Starter Kit shadcn UI components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Icons
import {
    Activity,
    Building2,
    CheckCircle2,
    Download,
    Edit3,
    Filter,
    MapPin,
    Phone,
    Plus,
    Radio,
    RotateCcw,
    Search,
    Shield,
    Tag,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-react';

// Types
import { Contact, ContactsPageProps } from '@/types/contacts';

type ContactFormData = {
    first_name: string;
    middle_initial: string;
    last_name: string;
    office: string;
    position: string;
    phone_number: string;
    category_id: number | string;
    barangay_id: number | string;
    is_active: boolean;
};

type CsvFormData = {
    csv_file: File | null;
};

export default function Index({ contacts, categories, barangays, filters, directoryStats, permissions }: ContactsPageProps) {
    const { flash } = usePage<ContactsPageProps>().props;

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');
    const [selectedBarangayFilter, setSelectedBarangayFilter] = useState<string>(filters.barangay_id || '');
    const [selectedCategoryFilter, setSelectedCategoryFilter] = useState<string>(filters.category_id || '');

    // Modal Visibility States
    const [isContactModalOpen, setIsContactModalOpen] = useState<boolean>(false);
    const [isEditMode, setIsEditMode] = useState<boolean>(false);
    const [editingContactId, setEditingContactId] = useState<number | null>(null);
    const [isImportModalOpen, setIsImportModalOpen] = useState<boolean>(false);

    // Dynamic Overview KPI Metrics
    const totalContacts = directoryStats.total;
    const activeContacts = directoryStats.active;
    const coveredBarangaysCount = directoryStats.barangays;

    // Form Hooks
    const contactForm = useForm<ContactFormData>({
        first_name: '',
        middle_initial: '',
        last_name: '',
        office: '',
        position: '',
        phone_number: '',
        category_id: categories.length > 0 ? categories[0].id : '',
        barangay_id: barangays.length > 0 ? barangays[0].id : '',
        is_active: true,
    });

    const csvForm = useForm<CsvFormData>({
        csv_file: null,
    });

    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('menu.contacts.index'),
            {
                search: searchTerm,
                barangay_id: selectedBarangayFilter,
                category_id: selectedCategoryFilter,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedBarangayFilter('');
        setSelectedCategoryFilter('');
        router.get(route('menu.contacts.index'), {}, { preserveState: true, replace: true });
    };

    const handleOpenCreateModal = () => {
        setIsEditMode(false);
        setEditingContactId(null);
        contactForm.reset();
        contactForm.clearErrors();
        setIsContactModalOpen(true);
    };

    const handleOpenEditModal = (contact: Contact) => {
        setIsEditMode(true);
        setEditingContactId(contact.id);
        contactForm.clearErrors();
        contactForm.setData({
            first_name: contact.first_name,
            middle_initial: contact.middle_initial || '',
            last_name: contact.last_name,
            office: contact.office || '',
            position: contact.position || '',
            phone_number: contact.phone_number,
            category_id: contact.category_id,
            barangay_id: contact.barangay_id ?? '',
            is_active: Boolean(contact.is_active),
        });
        setIsContactModalOpen(true);
    };

    const handleContactFormSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEditMode && editingContactId) {
            contactForm.put(route('menu.contacts.update', editingContactId), {
                onSuccess: () => {
                    setIsContactModalOpen(false);
                    contactForm.reset();
                },
            });
        } else {
            contactForm.post(route('menu.contacts.store'), {
                onSuccess: () => {
                    setIsContactModalOpen(false);
                    contactForm.reset();
                },
            });
        }
    };

    const handleDeleteContact = (contactId: number) => {
        if (confirm('Are you sure you want to remove this contact from active emergency dispatch?')) {
            router.delete(route('menu.contacts.destroy', contactId));
        }
    };

    const handleCsvImportSubmit = (e: FormEvent) => {
        e.preventDefault();
        csvForm.post(route('menu.contacts.import'), {
            onSuccess: () => {
                setIsImportModalOpen(false);
                csvForm.reset();
            },
        });
    };

    const getInitials = (firstName: string, lastName: string) => {
        return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
    };

    return (
        <AppLayout>
            <Head title="Emergency Contacts Directory" />

            <div className="space-y-6 text-slate-900">
                {/* 1. MINIMALIST HEADER BAR */}
                <div className="flex flex-col gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-2xl font-bold tracking-tight text-slate-900">Emergency Directory</h1>
                            <span className="inline-flex items-center gap-1 rounded-full border border-blue-200/60 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                <Radio className="h-3 w-3 text-blue-700" /> CDRRMO ILAGAN
                            </span>
                        </div>
                        <p className="mt-1 text-xs font-normal text-slate-500">
                            Official registry of responders, barangay leaders, and disaster emergency response teams.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={handleOpenCreateModal}
                            className="h-10 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-none transition-colors hover:bg-blue-700 hover:text-white focus-visible:ring-blue-600"
                        >
                            <Plus className="mr-1.5 h-3.5 w-3.5" />
                            Add Contact
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!permissions.administer}
                            title={permissions.administer ? 'Import contacts' : 'Administrator access required'}
                            onClick={() => setIsImportModalOpen(true)}
                            className="h-9 border border-blue-200 border-slate-200 bg-white text-xs font-medium text-blue-700 text-slate-700 shadow-none transition-colors hover:bg-blue-50 hover:bg-slate-50 hover:text-blue-800 focus-visible:ring-blue-600"
                        >
                            <Upload className="mr-1.5 h-3.5 w-3.5 text-slate-500" />
                            Import CSV / Excel
                        </Button>
                        {permissions.administer && (
                            <>
                                <a
                                    href={route('menu.contacts.template', { format: 'xlsx' })}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100"
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    Excel Template
                                </a>
                                <a
                                    href={route('menu.contacts.export', filters)}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    Export Contacts
                                </a>
                            </>
                        )}
                    </div>
                </div>

                {/* 2. MINIMAL KPI STATS OVERVIEW */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 transition-all hover:border-slate-300">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium tracking-wider text-slate-500 uppercase">Total Registered</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                <Users className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{totalContacts}</div>
                    </div>

                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 transition-all hover:border-slate-300">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium tracking-wider text-slate-500 uppercase">Active Recipients</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <Activity className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-semibold tracking-tight text-slate-900">{activeContacts}</span>
                            <span className="text-xs font-medium text-emerald-600">
                                {totalContacts > 0 ? Math.round((activeContacts / totalContacts) * 100) : 0}% active
                            </span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 transition-all hover:border-slate-300">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium tracking-wider text-slate-500 uppercase">Barangays Covered</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <MapPin className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-semibold tracking-tight text-slate-900">{coveredBarangaysCount}</span>
                            <span className="text-xs font-normal text-slate-500">/ {barangays.length} sectors</span>
                        </div>
                    </div>
                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 transition-all hover:border-slate-300">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium tracking-wider text-slate-500 uppercase">Categories</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <Tag className="h-4 w-4" aria-hidden="true" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{categories.length}</div>
                    </div>
                </div>

                {/* FLASH NOTIFICATION */}
                {flash?.success && (
                    <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 text-xs font-medium text-emerald-900">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                        <p>{flash.success}</p>
                    </div>
                )}

                {/* 3. MINIMAL FILTER TOOLBAR */}
                <div className="rounded-xl border border-slate-200/80 bg-white p-4">
                    <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <Label className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Search Directory</Label>
                            <div className="relative mt-1.5">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-500" />
                                <Input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    placeholder="Name, Phone, Office..."
                                    className="border-slate-200 bg-white pl-9 text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500 focus-visible:ring-1 focus-visible:ring-blue-600"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Barangay Sector</Label>
                            <select
                                value={selectedBarangayFilter}
                                onChange={(e) => setSelectedBarangayFilter(e.target.value)}
                                className="mt-1.5 w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-700 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-hidden"
                            >
                                <option value="">All Barangays</option>
                                {barangays.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <Label className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Response Category</Label>
                            <select
                                value={selectedCategoryFilter}
                                onChange={(e) => setSelectedCategoryFilter(e.target.value)}
                                className="mt-1.5 w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-700 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-hidden"
                            >
                                <option value="">All Categories</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-end gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                className="h-9 flex-1 bg-blue-600 text-sm font-semibold text-white shadow-none transition-colors hover:bg-blue-700 hover:text-white focus-visible:ring-blue-600"
                            >
                                <Filter className="mr-1.5 h-3.5 w-3.5" />
                                Apply Filters
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={handleResetFilters}
                                className="h-9 border-blue-200 border-slate-200 bg-white text-xs text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 focus-visible:ring-blue-600"
                            >
                                <RotateCcw className="h-3.5 w-3.5" /> Reset
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                    <span>
                        {contacts.total} matching contacts · Page {contacts.current_page} of {contacts.last_page}
                    </span>
                    <nav aria-label="Contact pagination" className="flex gap-3">
                        {contacts.prev_page_url && (
                            <Link href={contacts.prev_page_url} className="text-blue-700">
                                Previous
                            </Link>
                        )}
                        {contacts.next_page_url && (
                            <Link href={contacts.next_page_url} className="text-blue-700">
                                Next
                            </Link>
                        )}
                    </nav>
                </div>
                {/* 4. MINIMAL CONTACTS TABLE */}
                <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50/50 text-[11px] font-semibold text-slate-500 uppercase">
                                    <th className="px-4 py-3">Responder</th>
                                    <th className="px-4 py-3">Office & Position</th>
                                    <th className="px-4 py-3">Contact Info</th>
                                    <th className="px-4 py-3">Barangay</th>
                                    <th className="px-4 py-3">Category</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {contacts.data.length > 0 ? (
                                    contacts.data.map((contact) => (
                                        <tr key={contact.id} className="transition-colors hover:bg-slate-50/60">
                                            {/* Name + Avatar */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100 text-xs font-bold text-slate-700">
                                                        {getInitials(contact.first_name, contact.last_name)}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-slate-900">
                                                            {contact.last_name}, {contact.first_name}{' '}
                                                            {contact.middle_initial ? `${contact.middle_initial}.` : ''}
                                                        </p>
                                                        <span className="text-[10px] text-slate-500">ID: #{contact.id}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Office / Position */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-slate-900">{contact.position || 'Responder'}</span>
                                                    <span className="mt-0.5 flex items-center gap-1 text-[11px] text-slate-500">
                                                        <Building2 className="h-3 w-3 shrink-0 text-slate-500" /> {contact.office || 'N/A'}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Phone Number */}
                                            <td className="px-4 py-3.5">
                                                <a
                                                    href={`tel:${contact.phone_number}`}
                                                    className="inline-flex items-center gap-1.5 font-mono font-medium text-blue-700 hover:underline"
                                                >
                                                    <Phone className="h-3 w-3 text-slate-500" />
                                                    {contact.phone_number}
                                                </a>
                                            </td>

                                            {/* Barangay Badge */}
                                            <td className="px-4 py-3.5">
                                                <span className="inline-flex items-center gap-1 rounded-md border border-slate-200/60 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                                    <MapPin className="h-3 w-3 text-slate-500" />
                                                    {contact.barangay?.name || 'Not applicable'}
                                                </span>
                                            </td>

                                            {/* Category Badge */}
                                            <td className="px-4 py-3.5">
                                                <span className="inline-flex items-center gap-1 rounded-md border border-slate-200/60 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                                    <Tag className="h-3 w-3 text-slate-500" />
                                                    {contact.category?.name || 'General'}
                                                </span>
                                            </td>

                                            {/* Status Badge */}
                                            <td className="px-4 py-3.5">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold ${
                                                        contact.is_active
                                                            ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700'
                                                            : 'border-rose-200/60 bg-rose-50 text-rose-700'
                                                    }`}
                                                >
                                                    {contact.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>

                                            {/* Action Buttons */}
                                            <td className="px-4 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button
                                                        onClick={() => handleOpenEditModal(contact)}
                                                        className="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-white px-2.5 py-2 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600"
                                                        title="Edit Contact"
                                                    >
                                                        <Edit3 className="h-3.5 w-3.5" /> Edit
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteContact(contact.id)}
                                                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-2 text-xs font-medium text-slate-600 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-blue-600"
                                                        title="Delete Contact"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" /> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-16 text-center">
                                            <div className="mx-auto flex max-w-sm flex-col items-center justify-center text-center">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                                    <Search className="h-5 w-5" />
                                                </div>
                                                <p className="mt-3 text-xs font-semibold text-slate-900">No Contacts Found</p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    No emergency contact records matched your search query or filter settings.
                                                </p>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={handleResetFilters}
                                                    className="mt-4 border-blue-200 border-slate-200 bg-white text-xs text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 focus-visible:ring-blue-600"
                                                >
                                                    Clear Filters
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL 1: REGISTER / EDIT CONTACT */}
            {isContactModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/40 p-4 backdrop-blur-xs">
                    <div className="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-blue-600 text-white">
                                    <Shield className="h-3.5 w-3.5" />
                                </div>
                                <h2 className="text-sm font-bold text-slate-900">
                                    {isEditMode ? 'Edit Responder Details' : 'Register Emergency Contact'}
                                </h2>
                            </div>
                            <button onClick={() => setIsContactModalOpen(false)} className="text-slate-500 hover:text-slate-600">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleContactFormSubmit} className="space-y-4 text-xs">
                            <div className="grid grid-cols-5 gap-3">
                                <div className="col-span-2">
                                    <Label htmlFor="first_name" className="text-xs font-medium text-slate-700">
                                        First Name *
                                    </Label>
                                    <Input
                                        id="first_name"
                                        value={contactForm.data.first_name}
                                        onChange={(e) => contactForm.setData('first_name', e.target.value)}
                                        required
                                        className="mt-1 border-slate-200 bg-white text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                    />
                                    <InputError message={contactForm.errors.first_name} className="mt-1" />
                                </div>
                                <div className="col-span-1">
                                    <Label htmlFor="middle_initial" className="text-xs font-medium text-slate-700">
                                        M.I.
                                    </Label>
                                    <Input
                                        id="middle_initial"
                                        maxLength={2}
                                        value={contactForm.data.middle_initial}
                                        onChange={(e) => contactForm.setData('middle_initial', e.target.value)}
                                        className="mt-1 border-slate-200 bg-white text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                    />
                                </div>
                                <div className="col-span-2">
                                    <Label htmlFor="last_name" className="text-xs font-medium text-slate-700">
                                        Last Name *
                                    </Label>
                                    <Input
                                        id="last_name"
                                        value={contactForm.data.last_name}
                                        onChange={(e) => contactForm.setData('last_name', e.target.value)}
                                        required
                                        className="mt-1 border-slate-200 bg-white text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                    />
                                    <InputError message={contactForm.errors.last_name} className="mt-1" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="office" className="text-xs font-medium text-slate-700">
                                        Office / Organization
                                    </Label>
                                    <Input
                                        id="office"
                                        value={contactForm.data.office}
                                        onChange={(e) => contactForm.setData('office', e.target.value)}
                                        className="mt-1 border-slate-200 bg-white text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="position" className="text-xs font-medium text-slate-700">
                                        Position / Role
                                    </Label>
                                    <Input
                                        id="position"
                                        value={contactForm.data.position}
                                        onChange={(e) => contactForm.setData('position', e.target.value)}
                                        className="mt-1 border-slate-200 bg-white text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="phone_number" className="text-xs font-medium text-slate-700">
                                    Mobile Phone Number *
                                </Label>
                                <Input
                                    id="phone_number"
                                    placeholder="09XXXXXXXXX"
                                    value={contactForm.data.phone_number}
                                    onChange={(e) => contactForm.setData('phone_number', e.target.value)}
                                    required
                                    className="mt-1 border-slate-200 bg-white font-mono text-xs text-slate-900 shadow-none file:text-blue-700 placeholder:text-slate-500"
                                />
                                <InputError message={contactForm.errors.phone_number} className="mt-1" />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label className="text-xs font-medium text-slate-700">Barangay</Label>
                                    <select
                                        value={contactForm.data.barangay_id}
                                        onChange={(e) => contactForm.setData('barangay_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-700 focus:border-blue-600 focus:outline-hidden"
                                    >
                                        <option value="">Not applicable</option>
                                        {barangays.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label className="text-xs font-medium text-slate-700">Category *</Label>
                                    <select
                                        value={contactForm.data.category_id}
                                        onChange={(e) => contactForm.setData('category_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-700 focus:border-blue-600 focus:outline-hidden"
                                    >
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={contactForm.data.is_active}
                                    onChange={(e) => contactForm.setData('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                                />
                                <label htmlFor="is_active" className="text-xs font-medium text-slate-700">
                                    Contact is active and reachable
                                </label>
                            </div>

                            <div className="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsContactModalOpen(false)}
                                    className="border-blue-200 border-slate-200 bg-white text-xs text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 focus-visible:ring-blue-600"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={contactForm.processing}
                                    className="bg-blue-600 text-xs font-semibold text-white shadow-none hover:bg-blue-700 hover:text-white focus-visible:ring-blue-600"
                                >
                                    Save Record
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL 2: BULK IMPORT */}
            {isImportModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/40 p-4 backdrop-blur-xs">
                    <div className="w-full max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-blue-600 text-white">
                                    <Upload className="h-3.5 w-3.5" />
                                </div>
                                <h2 className="text-sm font-bold text-slate-900">Bulk Import Contacts</h2>
                            </div>
                            <button onClick={() => setIsImportModalOpen(false)} className="text-slate-500 hover:text-slate-600">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="space-y-2 rounded-lg border border-blue-100 bg-blue-50/50 p-3 text-xs leading-5 text-slate-600">
                            <p className="font-medium text-slate-800">Use the CSV or Excel template without changing its headers.</p>
                            <p>
                                Excel: fill the Contacts sheet using the Category and Barangay dropdowns, save as XLSX, and upload directly. Keep
                                phone numbers as text (09… or +639…). Blank barangay or Not applicable is allowed. Imported contacts are active.
                            </p>
                            <details>
                                <summary className="cursor-pointer font-medium text-blue-700">Category and barangay IDs</summary>
                                <div className="mt-2 grid max-h-40 grid-cols-2 gap-3 overflow-y-auto">
                                    <div>
                                        <p className="font-medium">category_id</p>
                                        {categories.map((category) => (
                                            <p key={category.id}>
                                                {category.id} — {category.name}
                                            </p>
                                        ))}
                                    </div>
                                    <div>
                                        <p className="font-medium">barangay_id</p>
                                        {barangays.map((barangay) => (
                                            <p key={barangay.id}>
                                                {barangay.id} — {barangay.name}
                                            </p>
                                        ))}
                                    </div>
                                </div>
                            </details>
                        </div>
                        <form onSubmit={handleCsvImportSubmit} className="space-y-4 text-xs">
                            <div>
                                <Label className="text-xs font-medium text-slate-700">Upload CSV or Excel File</Label>
                                <Input
                                    type="file"
                                    accept=".csv,.xlsx"
                                    required
                                    onChange={(e: ChangeEvent<HTMLInputElement>) => {
                                        if (e.target.files && e.target.files[0]) {
                                            csvForm.setData('csv_file', e.target.files[0]);
                                        }
                                    }}
                                    className="mt-2 border-slate-200 text-xs shadow-none"
                                />
                                <InputError message={csvForm.errors.csv_file} className="mt-1" />
                            </div>

                            <div className="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsImportModalOpen(false)}
                                    className="border-blue-200 border-slate-200 bg-white text-xs text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 focus-visible:ring-blue-600"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={csvForm.processing}
                                    className="bg-blue-600 text-xs font-semibold text-white shadow-none hover:bg-blue-700 hover:text-white focus-visible:ring-blue-600"
                                >
                                    Upload & Parse
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
