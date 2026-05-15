import os
import re

directory = '/var/www/crm.workbangers.com/bot/webapp'

for filename in os.listdir(directory):
    if filename.endswith('.php'):
        filepath = os.path.join(directory, filename)
        with open(filepath, 'r') as f:
            content = f.read()

        # Update version
        content = re.sub(r"\$APP_VERSION\s*=\s*'1\.0\.15';", r"$APP_VERSION = '1.0.16';", content)

        # We want to replace userLanguage with $userLanguage ONLY inside <?= ... ?>
        def replace_in_php(match):
            inner = match.group(1)
            # replace userLanguage with $userLanguage, but make sure we don't replace $userLanguage again
            inner = re.sub(r'(?<!\$)userLanguage', '$userLanguage', inner)
            return '<?=' + inner + '?>'

        new_content = re.sub(r'<\?=(.*?)\?>', replace_in_php, content, flags=re.DOTALL)

        if new_content != content:
            with open(filepath, 'w') as f:
                f.write(new_content)
            print(f"Updated {filename}")
