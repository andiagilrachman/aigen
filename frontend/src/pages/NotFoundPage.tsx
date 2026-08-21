import { Link } from 'react-router-dom';
import { Compass } from 'lucide-react';
import { Card, EmptyState } from '../components/ui';

/** Halaman untuk rute yang belum ada di fase 1. */
export function NotFoundPage() {
  return (
    <Card>
      <EmptyState
        icon={<Compass size={28} />}
        title="Halaman belum tersedia"
        description="Rilis pertama baru mencakup screener fundamental dan detail emiten."
      />
      <div className="text-center">
        <Link to="/screener" className="text-sm font-medium text-primary hover:underline">
          Buka screener
        </Link>
      </div>
    </Card>
  );
}
