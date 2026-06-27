import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Clock, MapPin, Search, Store, Utensils } from 'lucide-react';
import { useState } from 'react';

interface Restaurant {
    id: number;
    name: string;
    cuisine_type: string;
    // Odoo might not have open_time locally without customization, use defaults or specific fields
}

interface PageProps {
    restaurants: Restaurant[];
    filters: {
        search?: string;
    };
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daftar Restoran', href: '/restaurants' },
];

export default function RestaurantList({ restaurants = [], filters }: PageProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const filteredRestaurants = restaurants.filter((resto) =>
        resto.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (resto.cuisine_type && resto.cuisine_type.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pilih Restoran" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                    <div className="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 px-4 sm:px-0">
                        <div>
                            <h2 className="text-3xl font-bold text-gray-900">Mau Makan Di Mana?</h2>
                            <p className="text-gray-500 mt-1">Pilih restoran favoritmu di Amici Food Court.</p>
                        </div>

                        <div className="relative w-full md:w-96">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Search className="h-5 w-5 text-gray-400" />
                            </div>
                            <input
                                type="text"
                                className="pl-10 block w-full rounded-full border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 py-3 transition-shadow"
                                placeholder="Cari nama restoran atau menu..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                    </div>

                    {filteredRestaurants.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 px-4 sm:px-0">
                            {filteredRestaurants.map((resto) => (
                                <div key={resto.id} className="bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 overflow-hidden flex flex-col h-full group">

                                    <div className="h-40 bg-gradient-to-r from-orange-100 to-orange-50 flex items-center justify-center relative overflow-hidden">
                                        <Store className="w-16 h-16 text-orange-300 group-hover:scale-110 transition-transform duration-500" />
                                        <div className="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold text-gray-700 shadow-sm">
                                            10:00 - 22:00
                                        </div>
                                    </div>

                                    <div className="p-6 flex flex-col flex-grow">
                                        <div className="flex justify-between items-start mb-2">
                                            <h3 className="text-xl font-bold text-gray-900 group-hover:text-orange-600 transition-colors">
                                                {resto.name}
                                            </h3>
                                        </div>

                                        <p className="text-gray-500 text-sm mb-4 line-clamp-2 flex-grow">
                                            {resto.cuisine_type ? `Spesialisasi: ${resto.cuisine_type}` : 'Menyajikan berbagai hidangan lezat untuk Anda nikmati.'}
                                        </p>

                                        <div className="flex items-center gap-4 text-xs text-gray-400 mb-6 border-t border-gray-100 pt-4">
                                            <div className="flex items-center gap-1">
                                                <Clock className="w-3.5 h-3.5" />
                                                <span>Buka Setiap Hari</span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <MapPin className="w-3.5 h-3.5" />
                                                <span>Lantai 1</span>
                                            </div>
                                        </div>

                                        <Link
                                            href={`/reservation?tenant_id=${resto.id}`}
                                            className="w-full flex items-center justify-center gap-2 bg-gray-900 hover:bg-orange-600 text-white font-medium py-3 rounded-xl transition-colors duration-300"
                                        >
                                            <Utensils className="w-4 h-4" />
                                            Pesan Tempat
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-20">
                            <div className="bg-white p-8 rounded-full inline-block mb-4 shadow-sm">
                                <Utensils className="w-12 h-12 text-gray-300" />
                            </div>
                            <h3 className="text-lg font-medium text-gray-900">Restoran tidak ditemukan</h3>
                            <p className="text-gray-500">Coba cari dengan kata kunci lain.</p>
                        </div>
                    )}

                </div>
            </div>
        </AppLayout>
    );
}