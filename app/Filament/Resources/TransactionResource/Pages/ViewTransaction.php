<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Infolists\Components\Card;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Card::make([
                    TextEntry::make('invoice')
                        ->label('Invoice'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'pending' => 'warning',
                            'success' => 'success',
                            'expired' => 'gray',
                            'failed' => 'danger',
                            default => 'info',
                        }),
                    TextEntry::make('created_at')
                        ->label('Tanggal Transaksi')
                        ->dateTime(),
                ])->columns(3),

                Card::make([
                    TextEntry::make('customer.name')
                        ->label('Nama Customer'),
                    TextEntry::make('customer.email')
                        ->label('Email Customer'),
                    TextEntry::make('address')
                        ->label('Alamat Lengkap'),
                ])->columns(3),

                Card::make([
                    TextEntry::make('shipping.shipping_courier')
                        ->label('Jasa Pengiriman'),
                    TextEntry::make('shipping.shipping_service')
                        ->label('Layanan'),
                    TextEntry::make('shipping.shipping_cost')
                        ->label('Ongkos Kirim')
                        ->money('IDR', locale: 'id'),
                ])->columns(3),

                Card::make([
                    RepeatableEntry::make('transactionDetails')
                        ->label('Items / Produk Dibeli')
                        ->schema([
                            ImageEntry::make('product.image')
                                ->label('Gambar')
                                ->circular(),
                            TextEntry::make('product.title')
                                ->label('Nama Produk'),
                            TextEntry::make('qty')
                                ->label('Qty'),
                            TextEntry::make('price')
                                ->label('Subtotal')
                                ->money('IDR', locale: 'id'),
                        ])
                        ->columns(4),
                ]),

                Card::make([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('weight')
                                ->label('Total Berat (gram)'),
                            TextEntry::make('total')
                                ->label('Grand Total (Termasuk Ongkir)')
                                ->money('IDR', locale: 'id')
                                ->size(TextEntry\TextEntrySize::Large),
                        ]),
                ]),
            ]);
    }
}
