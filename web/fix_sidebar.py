import glob
import re
import os

files = glob.glob('/var/www/crm.workbangers.com/web/templates/dashboard/*.php')
files.extend(glob.glob('/var/www/crm.workbangers.com/web/templates/dashboard/components/*.php'))

for file in files:
    with open(file, 'r') as f:
        content = f.read()

    # We want to replace the body of function toggleSidebarCollapse() { ... }
    # Since it can span multiple lines, we use [\s\S]*?
    
    new_function = """function toggleSidebarCollapse() {
			const root = document.documentElement;
			const isCollapsed = root.getAttribute('data-sidebar') === 'collapsed';
			if (isCollapsed) root.removeAttribute('data-sidebar'); else root.setAttribute('data-sidebar', 'collapsed');
			localStorage.setItem('sidebarCollapsed', !isCollapsed ? 'true' : 'false');
			if (typeof map !== 'undefined' && map) setTimeout(() => map.invalidateSize(), 300);
		}"""

    # Replace variants
    new_content = re.sub(
        r"function\s+toggleSidebarCollapse\(\)\s*\{[\s\S]*?\}",
        new_function,
        content
    )
    
    # Let's also remove the trailing if (localStorage.getItem('sidebarCollapsed') === 'true') document.getElementById('sidebar').classList.add('collapsed');
    # which we might have missed
    new_content = re.sub(
        r"if\s*\(\s*localStorage\.getItem\('sidebarCollapsed'\)\s*===\s*'true'\s*\)\s*document\.getElementById\('sidebar'\)\.classList\.add\('collapsed'\);",
        "",
        new_content
    )

    if new_content != content:
        with open(file, 'w') as f:
            f.write(new_content)
        print('Updated', file)
