import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { type FormEvent, useRef, useState } from 'react';

export default function DeleteUser() {
    const [open, setOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({ password: '' });
    const close = () => {
        if (form.processing) return;
        setOpen(false);
        form.reset();
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.delete(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => form.reset(),
        });
    };
    return (
        <section className="border-t border-slate-100 pt-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 className="text-sm font-medium">Delete account</h3>
                    <p className="mt-1 text-xs text-slate-500">Permanently remove your sign-in account.</p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setOpen(true)}
                    className="rounded-lg border-red-200 bg-white text-red-700 hover:bg-red-50 hover:text-red-800"
                >
                    Delete account
                </Button>
            </div>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!value) close();
                }}
            >
                <DialogContent className="rounded-xl border-slate-200 bg-white text-slate-900 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete your account?</DialogTitle>
                        <DialogDescription className="text-slate-500">
                            This cannot be undone. Enter your current password to confirm. The last administrator must keep their account.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-5">
                        <div className="space-y-2">
                            <Label htmlFor="delete-account-password">Current password</Label>
                            <Input
                                id="delete-account-password"
                                ref={passwordInput}
                                type="password"
                                autoComplete="current-password"
                                required
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                className="rounded-lg border-slate-200 bg-white text-slate-900 focus-visible:ring-blue-100"
                            />
                            <InputError message={form.errors.password} />
                        </div>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={close}
                                className="border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} className="bg-red-600 text-white hover:bg-red-700">
                                {form.processing ? 'Deleting…' : 'Delete account'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
