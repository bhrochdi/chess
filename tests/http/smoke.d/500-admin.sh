# Administration : journal et tableau de bord refusés sans connexion.
new_session
expect_api 403 /api/admin.php '{"action":"journal"}' 'Connexion requise.'
expect_api 403 /api/admin.php '{"action":"dashboard"}' 'Connexion requise.'
new_session
