import { Suspense, lazy, useMemo } from 'react';
import { Circle } from 'lucide-react';
import type { LucideProps } from 'lucide-react';
import dynamicIconImports from 'lucide-react/dynamicIconImports';

/**
 * Ubah nama ikon yang tersimpan di kolom `nav_menu.icon` menjadi komponen.
 *
 * Nama ikon adalah data, bukan kode: admin bisa mengganti ikon menu lewat
 * database. Karena itu ikon dimuat lewat dynamic import — mengimpor seluruh
 * pustaka lucide hanya untuk berjaga-jaga akan menambah lebih dari 900 kB ke
 * bundel utama, padahal satu halaman paling banyak memakai belasan ikon.
 *
 * Bila nama tidak dikenali kita jatuh ke ikon netral daripada merusak sidebar.
 */

type IconName = keyof typeof dynamicIconImports;

// `name` di sini adalah nama ikon, bukan atribut SVG bawaan.
type DynamicIconProps = Omit<LucideProps, 'name'> & {
  name: string | null | undefined;
};

/** "LayoutDashboard" atau "Layout_Dashboard" -> "layout-dashboard". */
function toKebabCase(value: string): string {
  return value
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .replace(/[_\s]+/g, '-')
    .toLowerCase();
}

function resolve(name: string): IconName | null {
  if (name in dynamicIconImports) return name as IconName;

  const kebab = toKebabCase(name);
  return kebab in dynamicIconImports ? (kebab as IconName) : null;
}

export function DynamicIcon({ name, ...props }: DynamicIconProps) {
  const Icon = useMemo(() => {
    const key = name ? resolve(name) : null;
    return key ? lazy(dynamicIconImports[key]) : null;
  }, [name]);

  // Placeholder berukuran sama menahan tata letak agar tidak bergeser saat
  // ikon selesai dimuat.
  const placeholder = (
    <span
      aria-hidden
      style={{ width: props.size ?? 24, height: props.size ?? 24 }}
      className="inline-block shrink-0"
    />
  );

  if (!Icon) return <Circle {...props} />;

  return (
    <Suspense fallback={placeholder}>
      <Icon {...props} />
    </Suspense>
  );
}
