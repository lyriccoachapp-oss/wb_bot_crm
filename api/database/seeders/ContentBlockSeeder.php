<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContentBlock;

class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'key' => 'login_subtitle',
                'content' => [
                    'ru' => 'Управляйте данными вашей компании централизованно.',
                    'en' => 'Manage multiple API keys and track usage—all in one account.',
                    'uk' => 'Керуйте даними вашої компанії централізовано.'
                ]
            ],
            [
                'key' => 'terms',
                'content' => [
                    'ru' => 'Информация предназначена только для внутреннего использования и не будет передаваться сторонним лицам. Используя CRM, вы соглашаетесь с внутренними регламентами компании.',
                    'en' => 'Information is for internal use only and will not be shared with third parties. By using the CRM, you agree to the company\'s internal regulations.',
                    'uk' => 'Інформація призначена лише для внутрішнього використання і не передаватиметься стороннім особам. Використовуючи CRM, ви погоджуєтесь із внутрішніми регламентами компанії.'
                ]
            ],
            [
                'key' => 'privacy',
                'content' => [
                    'ru' => 'Мы надежно храним ваши данные и используем их только для обеспечения работы сервиса в соответствии с нашей политикой конфиденциальности.',
                    'en' => 'We securely store your data and use it only to ensure the operation of the service in accordance with our privacy policy.',
                    'uk' => 'Ми надійно зберігаємо ваші дані та використовуємо їх лише для забезпечення роботи сервісу відповідно до нашої політики конфіденційності.'
                ]
            ],
            [
                'key' => 'forgot_password',
                'content' => [
                    'ru' => 'Забыли пароль?',
                    'en' => 'Forgot password?',
                    'uk' => 'Забули пароль?'
                ]
            ],
            [
                'key' => 'sign_in',
                'content' => [
                    'ru' => 'Войти',
                    'en' => 'Sign in',
                    'uk' => 'Увійти'
                ]
            ],
            [
                'key' => 'password',
                'content' => [
                    'ru' => 'Пароль',
                    'en' => 'Password',
                    'uk' => 'Пароль'
                ]
            ],
            [
                'key' => 'email_label',
                'content' => [
                    'ru' => 'Почта',
                    'en' => 'Email',
                    'uk' => 'Електронна пошта'
                ]
            ],
            [
                'key' => 'or_separator',
                'content' => [
                    'ru' => 'или',
                    'en' => 'or',
                    'uk' => 'або'
                ]
            ],
            [
                'key' => 'continue_google',
                'content' => [
                    'ru' => 'Продолжить с Google',
                    'en' => 'Continue with Google',
                    'uk' => 'Продовжити з Google'
                ]
            ],
            [
                'key' => 'agree_terms',
                'content' => [
                    'ru' => 'Продолжая, вы соглашаетесь с ',
                    'en' => 'By continuing, you agree to the ',
                    'uk' => 'Продовжуючи, ви погоджуєтеся з '
                ]
            ],
            [
                'key' => 'terms_link',
                'content' => [
                    'ru' => 'Условиями использования',
                    'en' => 'Terms and Conditions',
                    'uk' => 'Умовами використання'
                ]
            ],
            [
                'key' => 'and_word',
                'content' => [
                    'ru' => ' и ',
                    'en' => ' and ',
                    'uk' => ' та '
                ]
            ],
            [
                'key' => 'privacy_link',
                'content' => [
                    'ru' => 'Политикой конфиденциальности',
                    'en' => 'Privacy Policy',
                    'uk' => 'Політикою конфіденційності'
                ]
            ],
        ];

        foreach ($blocks as $block) {
            ContentBlock::updateOrCreate(
                ['key' => $block['key']],
                ['content' => $block['content']]
            );
        }
    }
}
