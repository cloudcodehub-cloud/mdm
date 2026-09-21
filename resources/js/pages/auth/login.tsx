import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-emerald-700 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <button
                            type="submit"
                            className="sr-only"
                            tabIndex={-1}
                            disabled={processing}
                            aria-hidden="true"
                        >
                            Log In
                        </button>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Email
                                    <span
                                        className="text-destructive ml-0.5"
                                        aria-hidden="true"
                                    >
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    enterKeyHint="next"
                                    placeholder="name@organization.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        Password
                                        <span
                                            className="text-destructive ml-0.5"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot password
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    enterKeyHint="go"
                                    placeholder="Enter password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log In
                            </Button>
                        </div>

                        <p className="text-muted-foreground text-center text-sm">
                            Accounts are created by your organization
                            administrator.
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    description: 'Workforce access for MDM - Magic Data Management',
};
