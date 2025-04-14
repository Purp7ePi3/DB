</main>
    <footer>
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>Card Collector Center</h3>
                    <p>La piattaforma italiana per la compravendita di carte collezionabili.</p>
                </div>
                <div class="footer-column">
                    <h3>Link utili</h3>
                    <ul>
                        <li><a href="about.php">Chi siamo</a></li>
                        <li><a href="terms.php">Termini e condizioni</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="cookies.php">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Categorie</h3>
                    <ul>
                        <?php
                        // Query per ottenere alcuni giochi per il footer
                        $sql = "SELECT id, display_name FROM games ORDER BY display_name LIMIT 5";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo '<li><a href="game.php?id=' . $row["id"] . '">' . htmlspecialchars($row["display_name"]) . '</a></li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contatti</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-envelope"></i> info@cardcollector.it</li>
                        <li><i class="fas fa-phone"></i> +39 123 456 7890</li>
                        <li><i class="fas fa-map-marker-alt"></i> Via Roma 123, Milano</li>
                    </ul>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date("Y"); ?> Card Collector Center. Tutti i diritti riservati.</p>
            </div>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>