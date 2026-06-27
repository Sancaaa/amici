import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { login } from '@/routes';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function Register() {
    return (
        <AuthLayout title="" description="">
            <Head title="Register" />
            <div className="fixed inset-0 -z-50 overflow-hidden">
                <video
                    autoPlay
                    muted
                    loop
                    src="/asset/vid-login.mp4"
                    className="absolute top-0 left-0 w-full h-full object-cover"
                />
                <div className="absolute inset-0 bg-black/50" />
            </div>

            <div className="flex min-h-screen items-center justify-center px-4 py-12">
                <div className="w-full max-w-md overflow-hidden rounded-2xl border border-white/20 bg-black/30 p-8 shadow-2xl backdrop-blur-md">
                    <div className="mb-6 text-center">
                        <h2 className="text-3xl font-bold text-white tracking-wide">Create Account</h2>
                        <p className="mt-2 text-sm text-gray-300">
                            Bergabunglah bersama kami sekarang
                        </p>
                    </div>

                    <Form
                        {...RegisteredUserController.store.form()}
                        resetOnSuccess={['password', 'password_confirmation']}
                        disableWhileProcessing
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name" className="text-gray-200">Name</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="name"
                                            name="name"
                                            placeholder="Full name"
                                            className="border-white/20 bg-white/10 text-white placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email" className="text-gray-200">Email address</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            required
                                            tabIndex={2}
                                            autoComplete="email"
                                            name="email"
                                            placeholder="email@example.com"
                                            className="border-white/20 bg-white/10 text-white placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone_number" className="text-gray-200">Phone Number</Label>
                                        <Input
                                            id="phone"
                                            type="text"
                                            required
                                            tabIndex={3}
                                            autoComplete="tel"
                                            name="phone_number"
                                            placeholder="+62..."
                                            className="border-white/20 bg-white/10 text-white placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500"
                                        />
                                        <InputError message={errors.phone_number} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password" className="text-gray-200">Password</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            required
                                            tabIndex={4}
                                            autoComplete="new-password"
                                            name="password"
                                            placeholder="Password"
                                            className="border-white/20 bg-white/10 text-white placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation" className="text-gray-200">Confirm password</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            required
                                            tabIndex={5}
                                            autoComplete="new-password"
                                            name="password_confirmation"
                                            placeholder="Confirm password"
                                            className="border-white/20 bg-white/10 text-white placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500"
                                        />
                                        <InputError message={errors.password_confirmation} />
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-4 w-full bg-gray-600 py-6 text-base font-bold hover:bg-gray-700 transition-all duration-200 shadow-lg shadow-gray-900/20 border-none text-white"
                                        tabIndex={6}
                                        data-test="register-user-button"
                                        disabled={processing}
                                    >
                                        {processing && (
                                            <LoaderCircle className="h-5 w-5 animate-spin mr-2" />
                                        )}
                                        Create account
                                    </Button>
                                </div>

                                <div className="text-center text-sm text-gray-300 mt-2">
                                    Already have an account?{' '}
                                    <TextLink
                                        href={login()}
                                        tabIndex={7}
                                        className="font-semibold text-white underline decoration-gray-500 decoration-2 underline-offset-4 hover:text-gray-400 transition-colors"
                                    >
                                        Log in
                                    </TextLink>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </AuthLayout>
    );
}